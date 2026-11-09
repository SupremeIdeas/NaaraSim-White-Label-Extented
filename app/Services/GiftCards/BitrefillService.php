<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bitrefill gift cards — self-service tier (real public API, ~170 countries,
 * Basic auth, no sales onboarding required). Key-gated like every other
 * provider: blank api_id/api_secret means `available()` is false and sync is a
 * silent no-op (Admin → API keys flips it on the moment the keys are saved).
 *
 * Normalized to the SAME shape as Reloadly/Zendit so the catalogue sync,
 * pricing, and purchase path treat all four providers identically.
 *
 * Cost basis: Bitrefill's /products catalogue doesn't expose a wholesale rate
 * distinct from the package's face value the way Reloadly's discountPercentage
 * does — so (matching the existing, already-safe Zendit convention) face value
 * is used as the cost basis. GiftCardPricing then applies the real markup on
 * top, so retail is never below what we're actually charged. Revisit if a rate
 * field is confirmed once the account is live.
 */
class BitrefillService implements GiftCardProviderInterface, GiftCardStatusCheckable
{
    public function key(): string
    {
        return 'bitrefill';
    }

    public function available(): bool
    {
        return filled(config('services.bitrefill.api_key'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.bitrefill.base_url'), '/'))
            ->withToken((string) config('services.bitrefill.api_key'))
            ->acceptJson()->timeout(30);
    }

    /**
     * One page of the product list, inside Bitrefill's published limits (60 product requests a minute, 1,000 an hour). When the window is nearly spent we wait
     * for it to reset, and a 429 is retried after the wait the response asks for, so a full sync finishes instead of stopping half way with 0 products.
     */
    private function productPage(int $start): \Illuminate\Http\Client\Response
    {
        for ($try = 0; $try < 4; $try++) {
            $res = $this->client()->get('/products', ['limit' => 50, 'start' => $start]);
            if ($res->status() === 429) {
                $this->pause(max(2, min(70, (int) ($res->header('ratelimit-reset') ?: 60))));

                continue;
            }
            $res->throw();
            if ($res->header('ratelimit-remaining') !== '' && (int) $res->header('ratelimit-remaining') <= 2) {
                $this->pause(max(1, min(70, (int) ($res->header('ratelimit-reset') ?: 60))));
            }

            return $res;
        }

        return $res->throw();
    }

    private function pause(int $seconds): void
    {
        if (! app()->runningUnitTests()) {
            sleep($seconds);
        }
    }

    public function getCatalogue(): array
    {
        $out = [];
        $start = 0;
        $total = null;
        // Live v2 shape (2026-10-18): pages of 50 selected with `start` (a `limit` above 50 is capped), {meta:{total}, data:[...]}.
        do {
            $res = $this->productPage($start);
            $products = (array) ($res->json('data') ?? []);
            $total ??= (int) $res->json('meta.total');
            foreach ($products as $p) {
                // Only plain gift cards: a phone refill, account top-up, bill payment or eSIM needs a number, account or email we do not ask for here
                // (refills belong to the Airtime layer, eSIMs to the eSIM store), so selling one here would fail at order time.
                if (is_array($p) && (string) ($p['recipient_type'] ?? 'none') === 'none') {
                    $out[] = $this->map($p);
                }
            }
            $start += count($products);
        } while ($products !== [] && $start < max($total, 1) && $start <= 20000);

        return $out;
    }

    /** Bitrefill's `image` is a slug; the public asset is /content/cn/{slug}.png. (The wizard then stores its own normalised copy.) */
    private function imageUrl(mixed $image): ?string
    {
        $image = (string) $image;
        if ($image === '') {
            return null;
        }

        return str_starts_with($image, 'http') ? $image : 'https://www.bitrefill.com/content/cn/'.rawurlencode($image).'.png';
    }

    private function map(array $p): array
    {
        $brandName = (string) data_get($p, 'name', 'Gift card');
        $ccy = strtoupper((string) data_get($p, 'currency', 'USD'));
        $range = (array) data_get($p, 'range', []);
        $packages = (array) data_get($p, 'packages', []);
        $isRange = is_numeric(data_get($range, 'max')) && (float) $range['max'] > 0;

        return [
            'provider' => 'bitrefill',
            'provider_product_id' => (string) data_get($p, 'id'),
            'brand_key' => Str::slug($brandName),
            'brand_name' => $brandName,
            'country' => strtoupper((string) (data_get($p, 'country_code') ?? data_get($p, 'countryCode', ''))) ?: null,
            'currency' => $ccy ?: null,
            'denomination_type' => $isRange ? 'RANGE' : 'FIXED',
            'fixed_denominations' => $isRange ? [] : collect($packages)
                ->map(fn ($pkg) => data_get($pkg, 'value'))
                ->filter(fn ($v) => is_numeric($v) && (float) $v > 0)
                ->values()->all(),
            'min_amount' => $isRange ? (float) data_get($range, 'min', 0) : null,
            'max_amount' => $isRange ? (float) data_get($range, 'max', 0) : null,
            'logo_url' => $this->imageUrl(data_get($p, 'image')) ?: data_get($p, 'logoImage.url'),
            'brand_color' => preg_match('/^#[0-9a-f]{6}$/i', (string) data_get($p, 'logo_background')) ? data_get($p, 'logo_background') : null,
            'category' => (string) (data_get($p, 'categories.0') ?? data_get($p, 'category', '')) ?: null,
            // Bitrefill tags a card with SEVERAL categories ("pin" + "refill" is a phone top-up PIN): resolve from all of them, not just the first.
            'category_key' => \App\Support\GiftCardCategories::resolve(implode(' ', array_map('strval', (array) data_get($p, 'categories', []))), $brandName),
            // Bitrefill's create-invoice payload (product_id, package_id/value,
            // quantity, payment_method, external_id) doesn't confirm a required
            // recipient field the way Reloadly's recipientEmail does — left
            // empty rather than inventing one; delivery is to OUR account
            // (code/pin/link come back in the response), not emailed to a
            // third party.
            'required_fields' => [],
            'redeem_instruction' => (string) (data_get($p, 'instructions') ?: data_get($p, 'termsAndConditions', '')) ?: null,
            'cost_meta' => array_filter([
                'range_step' => $isRange ? data_get($range, 'step') : null,
            ], fn ($v) => $v !== null),
            'provider_enabled' => (bool) data_get($p, 'in_stock', data_get($p, 'enabled', true)),
            // Same currency-safety gate as Zendit: only sell where the charged
            // currency is USD (or unspecified), so an FX gap can never
            // undercharge (money-safety rule 1.4).
            'priceable' => $ccy === '' || $ccy === 'USD',
        ];
    }

    /**
     * The account balance in DOLLARS. Bitrefill holds a balance in one currency (BTC, USD or EUR). A USD balance is exact; a BTC balance is worth
     * whatever Bitcoin is worth right now, so it is converted at Bitrefill's own live rate (sats per dollar, read from a USD product). A balance in
     * any other currency is not counted, so we never read 0.002 BTC as two tenths of a cent, or 50 EUR as 50 dollars.
     */
    public function getBalance(): float
    {
        try {
            return (float) ($this->balanceDetail()['usd'] ?? 0.0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** @return array{amount: float, currency: string, usd: ?float, sats_per_usd: ?float} */
    public function balanceDetail(): array
    {
        $data = (array) ($this->client()->get('/accounts/balance')->throw()->json('data') ?? []);
        $amount = (float) ($data['balance'] ?? 0);
        $ccy = strtoupper((string) ($data['currency'] ?? 'USD'));
        $rate = null;
        $usd = match ($ccy) {
            'USD' => $amount,
            'BTC' => ($rate = $this->satsPerUsd()) !== null && $rate > 0 ? round($amount * 100000000 / $rate, 2) : null,
            default => null,
        };

        return ['amount' => $amount, 'currency' => $ccy, 'usd' => $usd, 'sats_per_usd' => $rate];
    }

    /** Bitrefill's live price of one dollar of face value, in satoshis (cached for a few minutes). */
    public function satsPerUsd(): ?float
    {
        return \Illuminate\Support\Facades\Cache::remember('bitrefill.sats_per_usd', 300, function () {
            foreach ((array) ($this->client()->get('/products', ['limit' => 50, 'start' => 0])->json('data') ?? []) as $p) {
                $r = (float) data_get($p, 'range.price_rate', 0);
                if (strtoupper((string) data_get($p, 'currency')) === 'USD' && $r > 0) {
                    return $r;
                }
                $pkg = (array) data_get($p, 'packages.0', []);
                if (strtoupper((string) data_get($p, 'currency')) === 'USD' && (float) ($pkg['value'] ?? 0) > 0 && (float) ($pkg['price'] ?? 0) > 0) {
                    return (float) $pkg['price'] / (float) $pkg['value'];
                }
            }

            return null;
        });
    }

    public function preflight(): array
    {
        $blank = ['ok' => false, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
        if (! $this->available()) {
            return ['error' => 'Add your Bitrefill API key on Admin → API keys first.'] + $blank;
        }

        try {
            $bal = (array) ($this->client()->get('/accounts/balance')->throw()->json('data') ?? []);
            $detail = $this->balanceDetail();
            $probe = $this->client()->get('/products', ['limit' => 1, 'start' => 0])->throw();
            $count = data_get($probe->json(), 'total') ?? data_get($probe->json(), 'meta.total');

            return [
                'ok' => true,
                'balance' => isset($bal['balance']) ? (float) $bal['balance'] : null,
                'currency' => $bal['currency'] ?? null,
                'usd_value' => $detail['usd'],
                'note' => match ($detail['currency']) {
                    'USD' => 'Balance is held in dollars: its value does not move with Bitcoin.',
                    'BTC' => 'Balance is held in Bitcoin: its dollar value moves with the Bitcoin price, and a fall costs you money. Top up a USD balance instead (pay with USDT or USDC and choose USD), so the rate is fixed once, at deposit.',
                    default => 'Balance is held in '.$detail['currency'].', which Naara does not spend from. Top up a USD balance.',
                },
                'products' => is_numeric($count) ? (int) $count : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthorized')
                ? 'Authentication failed — check the API key.'
                : $e->getMessage();

            return ['error' => mb_substr($msg, 0, 200)] + $blank;
        }
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        try {
            $res = $this->client()->post('/invoices', [
                'products' => [[
                    'product_id' => $providerProductId,
                    'value' => $amount,
                    'quantity' => 1,
                ]],
                'payment_method' => 'balance',
                'external_id' => $reference,
                'auto_pay' => true,
            ])->throw();

            // The live v2 reply is { meta, data: { id, status, payment, orders: [...] } }; the code itself is on the order, not the invoice.
            $inv = (array) ($res->json('data') ?? $res->json() ?? []);
            $resolved = $this->resolveInvoice($inv);

            return [
                'provider_tx_id' => (string) data_get($inv, 'id', ''),
                'status' => $resolved['status'],
                'receipt' => $resolved['receipt'],
            ];
        } catch (\Throwable $e) {
            throw new GiftCardProviderException('Bitrefill order failed: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Turn an invoice into our three states. "complete" means the invoice is finalised, not that its order succeeded, so the order is read (GET /orders/{id}) for
     * its own status and for `redemption_info` (the code, PIN, link and instructions). Anything not final stays "processing" so the reconcile job asks again.
     *
     * @param  array<string, mixed>  $inv  the invoice object (the `data` of a reply)
     * @return array{status: string, receipt: array<string, mixed>}
     */
    public function resolveInvoice(array $inv): array
    {
        $status = strtolower((string) ($inv['status'] ?? ''));
        if (in_array($status, ['denied', 'payment_error', 'expired', 'error', 'refunded', 'failed'], true)) {
            return ['status' => 'failed', 'receipt' => []];
        }
        if ($status !== 'complete') {
            return ['status' => 'processing', 'receipt' => []];
        }
        $first = (array) (data_get($inv, 'orders.0') ?? []);
        $order = $first;
        if (($oid = (string) ($first['id'] ?? '')) !== '') {
            $r = $this->client()->get('/orders/'.rawurlencode($oid));
            if ($r->ok()) {
                $order = (array) ($r->json('data') ?? $first);
            }
        }
        if (in_array(strtolower((string) ($order['status'] ?? '')), ['failed', 'refunded', 'error', 'denied'], true)) {
            return ['status' => 'failed', 'receipt' => []];
        }
        $info = $order['redemption_info'] ?? null;
        $info = is_array($info) ? $info : (is_string($info) && $info !== '' ? ['instructions' => $info] : []);
        $receipt = array_filter([
            'code' => $info['code'] ?? null,
            'epin' => $info['pin'] ?? null,
            'redemption_url' => $info['link'] ?? ($info['url'] ?? null),
            'instructions' => $info['instructions'] ?? null,
            'delivery_type' => ! empty($info['link']) ? 'link' : 'code',
        ], fn ($v) => $v !== null && $v !== '');
        $delivered = strtolower((string) ($order['status'] ?? '')) === 'delivered' || isset($receipt['code']) || isset($receipt['epin']) || isset($receipt['redemption_url']);

        return ['status' => $delivered ? 'delivered' : 'processing', 'receipt' => $receipt];
    }

    /**
     * Poll an invoice/order that's still 'processing' (real documented flow:
     * pay-with-balance normally completes inline, but the invoice can still be
     * mid-settlement) — used by the reconcile job so a webhook that never
     * lands isn't the only way a stuck order gets finalized.
     *
     * @return array{status: string, receipt: array<string, mixed>}
     */
    public function orderStatus(string $providerInvoiceId): array
    {
        $json = $this->client()->get('/invoices/'.rawurlencode($providerInvoiceId))->throw()->json();

        return $this->resolveInvoice((array) ($json['data'] ?? $json ?? []));
    }
}
