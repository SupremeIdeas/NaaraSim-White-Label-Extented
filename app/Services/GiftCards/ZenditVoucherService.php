<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Zendit Vouchers — the FAILOVER Naara Gift provider (same Zendit account/key as
 * the eSIM integration; inherits the queue-restart-on-key-save fix). Normalizes
 * the /vouchers/offers catalogue to the shared shape. Cost stays private.
 */
class ZenditVoucherService implements GiftCardProviderInterface
{
    public function key(): string
    {
        return 'zendit';
    }

    public function available(): bool
    {
        return filled(config('services.zendit.api_key'));
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.zendit.api_key'))
            ->baseUrl((string) config('services.zendit.base_url'))
            ->timeout(60);
    }

    public function getCatalogue(): array
    {
        $out = [];
        $offset = 0;
        $limit = 1024;
        do {
            $res = $this->client()->get('/vouchers/offers', ['_limit' => $limit, '_offset' => $offset])->throw();
            $offers = (array) ($res->json('list') ?? $res->json() ?? []);
            foreach ($offers as $o) {
                if (is_array($o)) {
                    $out[] = $this->map($o);
                }
            }
            $offset += $limit;
        } while (count($offers) === $limit && $offset <= 20480);

        $this->backfillLogos($out);

        return $out;
    }

    /**
     * Fills logo_url via GET /brands/{brand} — the gap the code has flagged
     * since Phase 1 ("fetched from /brands/{brand} in a later phase"). One
     * lookup per UNIQUE brand identifier (not per offer) to avoid N+1 against
     * a catalogue that can carry many offers per brand; capped so one bad
     * sync run can't balloon runtime, and any single brand's failure is
     * swallowed rather than failing the whole sync.
     *
     * @param  array<int, array<string, mixed>>  $rows  mutated in place
     */
    private function backfillLogos(array &$rows): void
    {
        $cache = [];
        $lookups = 0;
        $cap = 500;

        foreach ($rows as &$row) {
            $brandId = $row['_brand_lookup_key'] ?? null;
            unset($row['_brand_lookup_key']);
            if ($brandId === null || $brandId === '') {
                continue;
            }

            if (! array_key_exists($brandId, $cache)) {
                if ($lookups >= $cap) {
                    continue;
                }
                $lookups++;
                try {
                    $b = (array) $this->client()->get('/brands/'.rawurlencode($brandId))->throw()->json();
                    $cache[$brandId] = data_get($b, 'logoUrl') ?: data_get($b, 'logo') ?: data_get($b, 'imageUrl');
                } catch (\Throwable) {
                    $cache[$brandId] = null; // tolerate — never blocks the sync
                }
            }

            if ($cache[$brandId]) {
                $row['logo_url'] = $cache[$brandId];
            }
        }
    }

    private function map(array $o): array
    {
        $brandName = (string) ($o['brandName'] ?? $o['brand'] ?? 'Gift card');
        $divisor = (int) (data_get($o, 'price.currencyDivisor') ?: 1) ?: 1;
        $scale = fn ($v) => is_numeric($v) ? round(((float) $v) / $divisor, 2) : null;
        $ccy = strtoupper((string) (data_get($o, 'price.currency') ?? ''));

        return [
            'provider' => 'zendit',
            'provider_product_id' => (string) ($o['offerId'] ?? ''),
            'brand_key' => Str::slug($brandName),
            'brand_name' => $brandName,
            'country' => strtoupper((string) ($o['country'] ?? '')) ?: null,
            'currency' => (string) (data_get($o, 'price.currency') ?? '') ?: null,
            'denomination_type' => strtoupper((string) ($o['priceType'] ?? 'FIXED')),
            'fixed_denominations' => array_map(fn ($v) => $scale($v), (array) (data_get($o, 'price.fixed') ?? [])),
            'min_amount' => $scale(data_get($o, 'price.min')),
            'max_amount' => $scale(data_get($o, 'price.max')),
            'logo_url' => null, // filled by backfillLogos() after all offers are mapped
            // Internal-only key consumed (and stripped) by backfillLogos() —
            // never persisted, matches whichever identifier the offer actually
            // carries for the /brands/{id} lookup (verify against a live
            // account if brand identifiers turn out to be shaped differently).
            '_brand_lookup_key' => (string) ($o['brand'] ?? $o['brandId'] ?? '') ?: null,
            'brand_color' => null,
            'category' => (string) ($o['subType'] ?? $o['productType'] ?? '') ?: null,
            'required_fields' => $this->fields((array) ($o['requiredFields'] ?? [])),
            'redeem_instruction' => (string) ($o['notes'] ?? $o['shortNotes'] ?? '') ?: null,
            'cost_meta' => ['cost' => $o['cost'] ?? null, 'priceCurrency' => $ccy ?: null],
            'provider_enabled' => (bool) ($o['enabled'] ?? true),
            // Failover pricing is only trustworthy without an FX gap. Non-USD
            // Zendit offers are withheld rather than mispriced against a USD wallet.
            'priceable' => $ccy === '' || $ccy === 'USD',
        ];
    }

    public function getBalance(): float
    {
        try {
            $b = $this->client()->get('/balance')->json();
            $divisor = (int) ($b['currencyDivisor'] ?? 1) ?: 1;

            return (float) ($b['availableBalance'] ?? 0) / $divisor;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function preflight(): array
    {
        $blank = ['ok' => false, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
        if (! $this->available()) {
            return ['error' => 'Add your Zendit API key on Admin → API keys first.'] + $blank;
        }

        try {
            $bal = (array) $this->client()->get('/balance')->throw()->json();
            $divisor = (int) ($bal['currencyDivisor'] ?? 1) ?: 1;
            // Prove the vouchers catalogue is reachable with these keys.
            $this->client()->get('/vouchers/offers', ['_limit' => 1, '_offset' => 0])->throw();

            return [
                'ok' => true,
                'balance' => isset($bal['availableBalance']) ? (float) $bal['availableBalance'] / $divisor : null,
                'currency' => $bal['currency'] ?? null,
                'products' => null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthenticated')
                ? 'Authentication failed — check the Zendit API key and base URL.'
                : $e->getMessage();

            return ['error' => mb_substr($msg, 0, 200)] + $blank;
        }
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        try {
            $body = [
                'offerId' => $providerProductId,
                'transactionId' => $reference,
                'fields' => collect($fields)->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all(),
            ];
            // RANGE offers require the value; FIXED omit it.
            $body['value'] = ['type' => 'SEND', 'value' => (int) round($amount * 100)];

            $res = $this->client()->post('/vouchers/purchases', $body)->throw();
            $status = strtoupper((string) ($res->json('status') ?? 'PENDING'));
            $receipt = $this->receipt((array) ($res->json('receipt') ?? []));

            return [
                'provider_tx_id' => $reference,
                'status' => $this->mapStatus($status),
                'receipt' => $receipt,
            ];
        } catch (\Throwable $e) {
            throw new GiftCardProviderException('Zendit voucher purchase failed: '.$e->getMessage(), previous: $e);
        }
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'DONE' => 'delivered',
            'FAILED' => 'failed',
            default => 'processing',
        };
    }

    /** Normalize a Zendit receipt to the shared three-state shape. */
    public function receipt(array $r): array
    {
        return array_filter([
            'epin' => $r['epin'] ?? null,
            'redemption_url' => $r['redemptionUrl'] ?? null,
            'account_id' => $r['accountId'] ?? null,
            'instructions' => $r['instructions'] ?? null,
            'terms' => $r['terms'] ?? null,
            'expires_at' => $r['expiresAt'] ?? null,
            'delivery_type' => $r['deliveryType'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function fields(array $required): array
    {
        return collect($required)->map(fn ($f) => [
            'key' => is_array($f) ? ($f['key'] ?? $f['name'] ?? 'field') : (string) $f,
            'label' => is_array($f) ? ($f['label'] ?? ucfirst((string) ($f['key'] ?? 'Field'))) : ucfirst((string) $f),
            'type' => 'text',
            'required' => true,
        ])->values()->all();
    }
}
