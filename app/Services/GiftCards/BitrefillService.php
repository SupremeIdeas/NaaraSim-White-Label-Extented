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

    public function getCatalogue(): array
    {
        $out = [];
        $start = 0;
        $total = null;
        // Live v2 shape (2026-10-18): pages of 50 selected with `start` (a `limit` above 50 is capped), {meta:{total}, data:[...]}.
        do {
            $res = $this->client()->get('/products', ['limit' => 50, 'start' => $start])->throw();
            $products = (array) ($res->json('data') ?? []);
            $total ??= (int) $res->json('meta.total');
            foreach ($products as $p) {
                if (is_array($p)) {
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

    public function getBalance(): float
    {
        try {
            return (float) ($this->client()->get('/accounts/balance')->json('data.balance') ?? $this->client()->get('/accounts/balance')->json('balance', 0));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function preflight(): array
    {
        $blank = ['ok' => false, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
        if (! $this->available()) {
            return ['error' => 'Add your Bitrefill API key on Admin → API keys first.'] + $blank;
        }

        try {
            $bal = (array) ($this->client()->get('/accounts/balance')->throw()->json('data') ?? []);
            $probe = $this->client()->get('/products', ['limit' => 1, 'start' => 0])->throw();
            $count = data_get($probe->json(), 'total') ?? data_get($probe->json(), 'meta.total');

            return [
                'ok' => true,
                'balance' => isset($bal['balance']) ? (float) $bal['balance'] : null,
                'currency' => $bal['currency'] ?? null,
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

            $json = $res->json();
            $status = (string) data_get($json, 'status', 'unpaid');
            $order = (array) data_get($json, 'orders.0', []);

            $mapped = match ($status) {
                'complete' => 'delivered',
                'expired', 'error', 'refunded' => 'failed',
                default => 'processing', // unpaid, payment_detected, payment_confirmed
            };

            $receipt = array_filter([
                'code' => data_get($order, 'code'),
                'epin' => data_get($order, 'pin'),
                'redemption_url' => data_get($order, 'link'),
                'instructions' => data_get($order, 'instructions'),
                'delivery_type' => data_get($order, 'link') ? 'link' : 'code',
            ], fn ($v) => $v !== null && $v !== '');

            return [
                'provider_tx_id' => (string) data_get($json, 'id', ''),
                'status' => $mapped,
                'receipt' => $receipt,
            ];
        } catch (\Throwable $e) {
            throw new GiftCardProviderException('Bitrefill order failed: '.$e->getMessage(), previous: $e);
        }
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
        $json = $this->client()->get("/invoices/{$providerInvoiceId}")->throw()->json();
        $status = (string) data_get($json, 'status', 'unpaid');
        $order = (array) data_get($json, 'orders.0', []);

        $mapped = match ($status) {
            'complete' => 'delivered',
            'expired', 'error', 'refunded' => 'failed',
            default => 'processing',
        };

        $receipt = array_filter([
            'code' => data_get($order, 'code'),
            'epin' => data_get($order, 'pin'),
            'redemption_url' => data_get($order, 'link'),
            'instructions' => data_get($order, 'instructions'),
        ], fn ($v) => $v !== null && $v !== '');

        return ['status' => $mapped, 'receipt' => $receipt];
    }
}
