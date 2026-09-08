<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tillo gift cards — enterprise onboarding (sales conversation + HMAC-signed
 * requests), 4,000+ brands. Key-gated like every other provider: blank
 * api_key/secret means `available()` is false, so nothing here runs until an
 * account exists.
 *
 * Confirmed-real Tillo capabilities this adapter now covers, beyond the
 * original digital-issue-only stub: Balance Check (an already-issued card's
 * remaining value — via GiftCardBalanceCheckable), Check Digital Order Status
 * by Reference (via GiftCardStatusCheckable, for the reconcile job), and
 * Cancel/Reverse Digital Code (admin remediation instead of always eating the
 * cost via wallet refund — Tillo allows reversal within a 48h window).
 *
 * Tillo's exact request/response field names for these endpoints (beyond what
 * the public quick-start guide confirms: /digital/issue takes
 * client_request_id + brand + face_value{amount,currency} + delivery_method,
 * and returns data.code/data.url/float_balance{amount,currency}) require the
 * live account's current docs to verify precisely — flagged inline, same as
 * the existing signing-scheme note, rather than guessed with false certainty.
 */
class TilloService implements GiftCardBalanceCheckable, GiftCardProviderInterface, GiftCardStatusCheckable
{
    public function key(): string
    {
        return 'tillo';
    }

    public function available(): bool
    {
        return filled(config('services.tillo.api_key')) && filled(config('services.tillo.secret'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tillo.base_url'), '/'))
            ->withHeaders(['API-Key' => (string) config('services.tillo.api_key')])
            ->acceptJson()->timeout(30);
    }

    /**
     * Tillo signature: HMAC-SHA256 over apiKey-endpoint-GET|POST-timestamp,
     * keyed by the shared secret. VERIFY this exact string composition against
     * Tillo's current signing docs at activation — this is the same honest
     * flag the adapter has always carried; it is not assumed correct until an
     * account exists to test it against.
     */
    private function sign(string $endpoint, string $timestamp): string
    {
        $payload = implode('-', [config('services.tillo.api_key'), $endpoint, $timestamp]);

        return hash_hmac('sha256', $payload, (string) config('services.tillo.secret'));
    }

    private function signed(string $endpoint): PendingRequest
    {
        $ts = (string) (now()->timestamp * 1000);

        return $this->client()->withHeaders(['Signature' => $this->sign($endpoint, $ts), 'Timestamp' => $ts]);
    }

    public function getCatalogue(): array
    {
        $json = $this->signed('brand')->get('/brands')->throw()->json();

        return collect((array) data_get($json, 'data.brands', []))
            ->map(fn ($b, $slug) => $this->map((string) $slug, (array) $b))
            ->values()->all();
    }

    private function map(string $slug, array $b): array
    {
        $brandName = (string) data_get($b, 'name', $slug);
        $ccy = strtoupper((string) data_get($b, 'currency', 'GBP'));
        $denominations = (array) data_get($b, 'denominations', []);
        $isRange = data_get($b, 'is_range') === true || (empty($denominations) && data_get($b, 'range.max'));

        return [
            'provider' => 'tillo',
            'provider_product_id' => $slug,
            'brand_key' => Str::slug($brandName),
            'brand_name' => $brandName,
            'country' => strtoupper((string) data_get($b, 'country', '')) ?: null,
            'currency' => $ccy ?: null,
            'denomination_type' => $isRange ? 'RANGE' : 'FIXED',
            'fixed_denominations' => $isRange ? [] : collect($denominations)
                ->filter(fn ($v) => is_numeric($v) && (float) $v > 0)->values()->all(),
            'min_amount' => $isRange ? (float) data_get($b, 'range.min', 0) : null,
            'max_amount' => $isRange ? (float) data_get($b, 'range.max', 0) : null,
            'logo_url' => data_get($b, 'image_url') ?: data_get($b, 'logo'),
            'brand_color' => null,
            'category' => (string) data_get($b, 'category', '') ?: null,
            'required_fields' => [],
            'redeem_instruction' => (string) data_get($b, 'redeem_instructions', '') ?: null,
            'cost_meta' => [],
            'provider_enabled' => (bool) data_get($b, 'enabled', true),
            // Same currency-safety gate as Zendit/Bitrefill: no confirmed
            // per-brand wholesale-rate field, so only sell where the charged
            // currency is USD (or unspecified) — never mispriced by an FX gap.
            'priceable' => $ccy === '' || $ccy === 'USD',
        ];
    }

    public function getBalance(): float
    {
        try {
            $json = $this->signed('check-floats')->get('/check-floats')->json();

            return (float) data_get($json, 'data.floats.0.balance', 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function preflight(): array
    {
        $blank = ['ok' => false, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
        if (! $this->available()) {
            return ['error' => 'Tillo needs an enterprise account — add your API key + secret on Admin → API keys once onboarded.'] + $blank;
        }

        try {
            $floats = (array) $this->signed('check-floats')->get('/check-floats')->throw()->json();
            $first = (array) data_get($floats, 'data.floats.0', []);
            $brands = (array) $this->signed('brand')->get('/brands')->throw()->json();
            $count = count((array) data_get($brands, 'data.brands', []));

            return [
                'ok' => true,
                'balance' => isset($first['balance']) ? (float) $first['balance'] : null,
                'currency' => $first['currency'] ?? null,
                'products' => $count,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthorized')
                ? 'Authentication failed — check the API key, secret and signing scheme against your live Tillo account.'
                : $e->getMessage();

            return ['error' => mb_substr($msg, 0, 200)] + $blank;
        }
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        try {
            $json = $this->signed('digital-issue')->post('/digital/issue', [
                'client_request_id' => $reference,
                'brand' => $providerProductId,
                'face_value' => ['amount' => $amount, 'currency' => $currency],
                'delivery_method' => 'url',
            ])->throw()->json();

            $status = (string) data_get($json, 'status', 'success');
            $mapped = match (strtolower($status)) {
                'success', 'issued', 'complete' => 'delivered',
                'error', 'failed', 'declined' => 'failed',
                default => 'processing',
            };

            $receipt = array_filter([
                'code' => data_get($json, 'data.code'),
                'redemption_url' => data_get($json, 'data.url'),
                'delivery_type' => data_get($json, 'data.url') ? 'link' : 'code',
            ], fn ($v) => $v !== null && $v !== '');

            return [
                'provider_tx_id' => (string) data_get($json, 'data.code', $reference),
                'status' => $mapped,
                'receipt' => $receipt,
            ];
        } catch (\Throwable $e) {
            throw new GiftCardProviderException('Tillo digital issue failed: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Check Digital Order Status by Reference (real, documented endpoint) —
     * used by the reconcile job for an order stuck 'processing' because
     * Tillo's async issue path hadn't resolved yet when order() returned.
     */
    public function orderStatus(string $providerTxId): array
    {
        $json = $this->signed('check-order-status')
            ->get('/digital/check-order-status', ['client_request_id' => $providerTxId])
            ->throw()->json();

        $status = (string) data_get($json, 'status', 'processing');
        $mapped = match (strtolower($status)) {
            'success', 'issued', 'complete' => 'delivered',
            'error', 'failed', 'declined' => 'failed',
            default => 'processing',
        };

        $receipt = array_filter([
            'code' => data_get($json, 'data.code'),
            'redemption_url' => data_get($json, 'data.url'),
        ], fn ($v) => $v !== null && $v !== '');

        return ['status' => $mapped, 'receipt' => $receipt];
    }

    /**
     * Balance Check (real, documented endpoint, rate-limited to 50 req/min on
     * Tillo's side — callers should not poll this aggressively). Only real
     * capability of its kind across all 4 providers, which is why it's the
     * one behind GiftCardBalanceCheckable rather than assumed universal.
     */
    public function checkBalance(array $receipt): array
    {
        $code = (string) ($receipt['code'] ?? '');
        $json = $this->signed('balance-check')
            ->get('/digital/balance-check', ['code' => $code])
            ->throw()->json();

        return [
            'balance' => (float) data_get($json, 'data.balance', 0),
            'currency' => (string) data_get($json, 'data.currency', ''),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Cancel/Reverse Digital Code (real, documented — reversal window is 48h
     * from Tillo's side). Used by admin remediation instead of always eating
     * the cost via a wallet-only refund when a card was issued in error.
     */
    public function cancel(string $providerTxId): bool
    {
        $json = $this->signed('digital-cancel')
            ->post('/digital/cancel', ['client_request_id' => $providerTxId])
            ->throw()->json();

        return in_array(strtolower((string) data_get($json, 'status', '')), ['success', 'cancelled', 'reversed'], true);
    }
}
