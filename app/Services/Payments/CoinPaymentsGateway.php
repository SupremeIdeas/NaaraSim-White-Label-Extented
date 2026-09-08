<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * CoinPayments (blueprint Section 14.2 — crypto rail). Documented merchant API:
 * POST cmd=create_transaction to api.php with an HMAC-SHA512 of the urlencoded
 * body in the `HMAC` header (private key), returning a checkout_url. The IPN is
 * the same scheme — `HMAC` header over the raw body with the IPN secret — and
 * the merchant id must match (money-safety rule 9). USD-priced; the buyer pays
 * in the configured coin, credited to the USD wallet. Degrades safely off.
 */
class CoinPaymentsGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'coinpayments';
    }

    private function configured(): bool
    {
        return ! empty(config('services.coinpayments.public_key'))
            && ! empty(config('services.coinpayments.private_key'));
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $body = [
            'version' => 1,
            'cmd' => 'create_transaction',
            'key' => (string) config('services.coinpayments.public_key'),
            'amount' => round($amount, 2),
            'currency1' => strtoupper($currency),                          // priced in fiat
            'currency2' => (string) config('services.coinpayments.pay_currency', 'USDT.TRC20'), // paid in crypto
            'buyer_email' => $user->email,
            'item_name' => 'NaaraSim wallet top-up',
            'custom' => $user->id.':'.$reference,                          // echoed back in the IPN
            'ipn_url' => route('webhooks.payments', 'coinpayments'),
            'success_url' => $meta['return_url'] ?? config('app.url').'/wallet',
            'cancel_url' => $meta['cancel_url'] ?? config('app.url').'/wallet',
        ];
        $encoded = http_build_query($body);

        $response = Http::asForm()
            ->withHeaders(['HMAC' => hash_hmac('sha512', $encoded, (string) config('services.coinpayments.private_key'))])
            ->withBody($encoded, 'application/x-www-form-urlencoded')
            ->post('https://www.coinpayments.net/api.php')
            ->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) data_get($response, 'result.checkout_url', ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('HMAC');
        $secret = (string) config('services.coinpayments.ipn_secret');
        $merchant = (string) config('services.coinpayments.merchant_id');
        if (! is_string($signature) || $secret === '') {
            return false;
        }
        // The IPN must be for our merchant account.
        if ($merchant !== '' && (string) $request->input('merchant', '') !== $merchant) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        // status >= 100 (or 2) means the payment is complete.
        $status = (int) $request->input('status', -1);
        $success = $status >= 100 || $status === 2;

        [$userId, $reference] = array_pad(explode(':', (string) $request->input('custom', ''), 2), 2, null);

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) ($reference ?? ''),
            userId: $userId !== null && $userId !== '' ? (int) $userId : null,
            amount: (float) $request->input('amount1', 0),               // amount in currency1 (fiat)
            currency: strtoupper((string) $request->input('currency1', 'USD')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
