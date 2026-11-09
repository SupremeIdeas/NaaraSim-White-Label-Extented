<?php

namespace App\Services\Topup\Providers;

use App\Services\Topup\Concerns\LogsProviderCalls;
use App\Services\Topup\Contracts\TopupProviderInterface;
use App\Services\Topup\DTO\ProductDTO;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\DTO\RecipientInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zendit mobile top-ups (same account/key as the eSIM + gift integrations). Verified against the live API: GET /topups/offers?country=XX
 * returns every operator's airtime + data bundle with cost in USD; POST /topups/purchases + GET /topups/purchases/{id} + the purchase
 * webhook. Amounts are integers divided by `currencyDivisor`.
 */
class ZenditTopupProvider implements TopupProviderInterface
{
    use LogsProviderCalls;

    public function code(): string
    {
        return 'zendit';
    }

    public function name(): string
    {
        return 'Zendit';
    }

    public function configured(): bool
    {
        return filled(config('services.zendit.api_key'));
    }

    public function newReference(): string
    {
        return 'nt-'.Str::lower((string) Str::ulid());
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.zendit.api_key'))->acceptJson()
            ->baseUrl(rtrim((string) config('services.zendit.base_url'), '/'))->timeout(45);
    }

    /** Zendit has no number-lookup endpoint: the customer confirms the operator from the live list. */
    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        return null;
    }

    public function countries(): array
    {
        return Cache::remember('topup.zendit.countries', 21600, function () {
            $seen = [];
            $offset = 0;
            try {
                do {
                    $r = $this->send($this->client(), 'GET', '/topups/offers', ['_limit' => 1024, '_offset' => $offset])->throw();
                    $list = (array) $r->json('list', []);
                    foreach ($list as $o) {
                        if (! empty($o['country']) && ($o['enabled'] ?? true)) {
                            $seen[strtoupper($o['country'])] = true;
                        }
                    }
                    $offset += 1024;
                } while (count($list) === 1024 && $offset < 40960);
            } catch (Throwable) {
                return [];
            }

            return array_keys($seen);
        });
    }

    public function listProducts(string $countryIso): array
    {
        $out = [];
        $offset = 0;
        do {
            $r = $this->send($this->client(), 'GET', '/topups/offers', ['country' => strtoupper($countryIso), '_limit' => 1024, '_offset' => $offset])->throw();
            $list = (array) $r->json('list', []);
            foreach ($list as $o) {
                if (is_array($o) && ($o['enabled'] ?? true) && ($dto = $this->map($o))) {
                    $out[] = $dto;
                }
            }
            $offset += 1024;
        } while (count($list) === 1024 && $offset < 8192);

        return $out;
    }

    /** @param  array<string, mixed>  $o */
    private function map(array $o): ?ProductDTO
    {
        $sub = strtolower(implode(' ', (array) ($o['subTypes'] ?? [])));
        $gb = (float) ($o['dataGB'] ?? 0);
        $unl = (bool) ($o['dataUnlimited'] ?? false);
        $type = (str_contains($sub, 'data') || str_contains($sub, 'bundle') || $gb > 0 || $unl) ? 'data' : 'airtime';
        $sd = max(1, (int) ($o['send']['currencyDivisor'] ?? 100));
        $cd = max(1, (int) ($o['cost']['currencyDivisor'] ?? 100));
        $fixed = strtoupper((string) ($o['priceType'] ?? 'FIXED')) === 'FIXED';
        $days = (int) ($o['durationDays'] ?? 0);

        $name = trim((string) ($o['shortNotes'] ?? ''));
        if ($name === '') {
            $name = $type === 'data'
                ? trim(($unl ? 'Unlimited data' : rtrim(rtrim(number_format($gb, 2), '0'), '.').' GB').($days ? " ({$days} days)" : ''))
                : 'Airtime';
        }

        $dto = new ProductDTO(
            providerSku: (string) $o['offerId'], countryIso: (string) $o['country'], operatorName: (string) ($o['brandName'] ?? $o['brand'] ?? 'Operator'),
            type: $type, name: $name, currency: (string) ($o['send']['currency'] ?? 'USD'), isFixed: $fixed,
            operatorCode: (string) ($o['brand'] ?? ''), validityText: $days ? $days.' day'.($days > 1 ? 's' : '') : null,
            dataMb: $gb > 0 ? (int) round($gb * 1024) : null, dataUnlimited: $unl, costCurrency: (string) ($o['cost']['currency'] ?? 'USD'),
            meta: ['discount' => $o['cost']['discount'] ?? null, 'voice' => $o['voiceMinutes'] ?? 0, 'sms' => $o['smsNumber'] ?? 0],
        );

        if ($fixed) {
            if (! isset($o['send']['fixed'], $o['cost']['fixed'])) {
                return null;
            }
            $dto->fixedAmount = $o['send']['fixed'] / $sd;
            $dto->costNative = $o['cost']['fixed'] / $cd;
        } else {
            $fx = (float) ($o['cost']['fx'] ?? 0);
            if ($fx <= 0 || ! isset($o['send']['min'], $o['send']['max'])) {
                return null;
            }
            $dto->minAmount = $o['send']['min'] / $sd;
            $dto->maxAmount = $o['send']['max'] / $sd;
            $dto->costRatio = 1 / $fx;                      // USD of cost per 1 unit the recipient receives
            $pfx = (float) ($o['price']['fx'] ?? 0);
            $dto->meta['price_ratio'] = $pfx > 0 ? 1 / $pfx : null;
        }

        return $dto;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        $body = ['transactionId' => $req->reference, 'offerId' => $req->providerSku, 'recipientPhoneNumber' => $req->phoneE164];
        if (! $req->isFixed) {
            // RANGE offers: value.type PRICE, in price-currency minor units. ⚠ exact semantics unverified (needs a funded sandbox run).
            $ratio = (float) ($req->meta['price_ratio'] ?? 0);
            $body['value'] = ['type' => 'PRICE', 'value' => (int) round($req->amount * $ratio * 100)];
        }
        try {
            $r = $this->send($this->client(), 'POST', '/topups/purchases', $body, $req->reference);
        } catch (Throwable $e) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Network error: requery before anything else.');
        }
        if ($r->serverError()) {
            return new PurchaseResult(PurchaseResult::PENDING, $req->reference, message: 'Supplier error '.$r->status().' (will requery).', raw: (array) $r->json());
        }
        if ($r->failed()) {
            // A 4xx could be "duplicate transactionId" for an order already in flight: ask before declaring failure.
            $q = $this->requery($req->reference);
            if ($q->status !== PurchaseResult::PENDING || $q->providerTxnId !== null) {
                return $q;
            }

            return new PurchaseResult(PurchaseResult::FAILED, $req->reference, providerCode: (string) $r->json('errorCode'), message: (string) ($r->json('message') ?: 'Rejected by supplier.'), definitive: true, raw: (array) $r->json());
        }

        return $this->fromPurchase((array) $r->json(), $req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        try {
            $r = $this->send($this->client(), 'GET', '/topups/purchases/'.rawurlencode($reference), [], $reference);
        } catch (Throwable) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Network error.');
        }
        if ($r->status() === 404) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier has no record of this reference.');
        }
        if (! $r->successful()) {
            return new PurchaseResult(PurchaseResult::PENDING, $reference, message: 'Supplier status '.$r->status());
        }

        return $this->fromPurchase((array) $r->json(), $reference);
    }

    /** @param  array<string, mixed>  $j */
    private function fromPurchase(array $j, string $ref): PurchaseResult
    {
        $st = strtoupper((string) ($j['status'] ?? ''));
        $status = match ($st) {
            'DONE' => PurchaseResult::DELIVERED,
            'FAILED' => PurchaseResult::FAILED,
            default => PurchaseResult::PENDING,           // PENDING / ACCEPTED / AUTHORIZED / IN_PROGRESS / anything unknown
        };
        $div = max(1, (int) ($j['costCurrencyDivisor'] ?? 100));
        $conf = (array) ($j['confirmation'] ?? []);

        return new PurchaseResult(
            $status, $ref, (string) ($j['transactionId'] ?? $ref),
            $st ?: null, (string) ($j['error']['message'] ?? $j['error']['description'] ?? ''), isset($j['cost']) ? $j['cost'] / $div : null,
            $j['costCurrency'] ?? null, definitive: $status === PurchaseResult::FAILED, receiptText: $conf['confirmationNumber'] ?? null, raw: $j,
        );
    }

    public function balance(): array
    {
        $r = $this->send($this->client(), 'GET', '/balance')->throw();
        $j = (array) $r->json();
        $div = max(1, (int) ($j['divisor'] ?? $j['currencyDivisor'] ?? 100));

        return ['amount' => (float) (($j['availableBalance'] ?? $j['balance'] ?? 0) / $div), 'currency' => (string) ($j['currency'] ?? 'USD')];
    }

    /**
     * Zendit documents no signature scheme for this webhook, so authenticity is the shared secret in the URL/header (hash_equals) AND the
     * result is never trusted blindly: the order is always re-read from the supplier by the job that processes it.
     */
    public function parseWebhook(Request $request): ?PurchaseResult
    {
        $secret = (string) config('services.zendit.webhook_secret');
        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->query('secret', ''));
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return null;
        }
        $j = (array) $request->json()->all();
        if (empty($j['transactionId']) || ! isset($j['status'])) {
            return null;
        }

        return $this->fromPurchase($j, (string) $j['transactionId']);
    }
}
