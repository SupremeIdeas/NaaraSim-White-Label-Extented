<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * NOWPayments (blueprint Section 14.2 — crypto rail). Documented API: create an
 * invoice (x-api-key header) and redirect to invoice_url. The IPN webhook is
 * signed HMAC-SHA512 over the JSON body with keys sorted recursively, using the
 * IPN secret (money-safety rule 9) — verified before the payload is trusted.
 * USD-priced; a crypto settlement credits the USD wallet. Degrades safely off.
 */
class NowPaymentsGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'nowpayments';
    }

    private function configured(): bool
    {
        return ! empty(config('services.nowpayments.api_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.nowpayments.base_url', 'https://api.nowpayments.io'), '/');
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withHeaders(['x-api-key' => (string) config('services.nowpayments.api_key')])
            ->acceptJson()
            ->post($this->base().'/v1/invoice', [
                'price_amount' => round($amount, 2),
                'price_currency' => strtolower($currency),
                // order_id carries "<user_id>:<reference>" so the IPN credits the
                // right wallet (the invoice API echoes order_id back verbatim).
                'order_id' => $user->id.':'.$reference,
                'order_description' => 'NaaraSim wallet top-up',
                'ipn_callback_url' => route('webhooks.payments', 'nowpayments'),
                'success_url' => $meta['return_url'] ?? config('app.url').'/wallet',
                'cancel_url' => $meta['cancel_url'] ?? config('app.url').'/wallet',
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) ($response['invoice_url'] ?? ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('x-nowpayments-sig');
        $secret = (string) config('services.nowpayments.ipn_secret');
        if (! is_string($signature) || $secret === '') {
            return false;
        }

        $sorted = $this->ksortRecursive($request->json()->all());

        // NOWPayments' canonical Python signs the sorted JSON with compact
        // separators and UNESCAPED slashes (json.dumps never escapes "/"), but
        // their own WooCommerce PHP plugin re-encodes with slashes ESCAPED.
        // Accept either — both are HMAC'd with the secret IPN key, so this can
        // never weaken security, it only stops a valid credit being silently
        // rejected on the slash-escaping nuance.
        foreach ([JSON_UNESCAPED_SLASHES, 0] as $flags) {
            $expected = hash_hmac('sha512', (string) json_encode($sorted, $flags), $secret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $status = (string) $request->input('payment_status', '');
        $success = in_array($status, ['finished', 'confirmed'], true);

        // order_id = "<user_id>:<reference>".
        [$userId, $reference] = array_pad(explode(':', (string) $request->input('order_id', ''), 2), 2, null);

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) ($reference ?? ''),
            userId: $userId !== null && $userId !== '' ? (int) $userId : null,
            amount: (float) $request->input('price_amount', 0),
            currency: strtoupper((string) $request->input('price_currency', 'USD')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }

    /** Recursively sort array keys (NOWPayments signs the key-sorted JSON). */
    private function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->ksortRecursive($v);
            }
        }

        return $arr;
    }
}
