<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Payssion (blueprint Section 14.2 — local-methods rail). Documented API: POST
 * payment/create with an api_sig = md5("api_key|pm_id|amount|currency|order_id|
 * secret_key"), returning a redirect_url. The webhook is verified with the same
 * scheme plus the paid state (notify_sig, money-safety rule 9) before trust.
 * USD-priced. Degrades safely when unconfigured.
 */
class PayssionGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'payssion';
    }

    private function configured(): bool
    {
        return ! empty(config('services.payssion.api_key'))
            && ! empty(config('services.payssion.secret_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.payssion.base_url', 'https://www.payssion.com'), '/');
    }

    private function pmId(): string
    {
        return (string) config('services.payssion.pm_id', 'alipay_cn');
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();
        $orderId = $user->id.':'.$reference; // echoed back in the webhook
        $amountStr = number_format($amount, 2, '.', '');
        $currency = strtoupper($currency);
        $apiKey = (string) config('services.payssion.api_key');
        $secret = (string) config('services.payssion.secret_key');

        $sig = md5(implode('|', [$apiKey, $this->pmId(), $amountStr, $currency, $orderId, $secret]));

        $response = Http::asForm()->acceptJson()
            ->post($this->base().'/api/v1/payment/create', [
                'api_key' => $apiKey,
                'pm_id' => $this->pmId(),
                'amount' => $amountStr,
                'currency' => $currency,
                'order_id' => $orderId,
                'description' => 'NaaraSim wallet top-up',
                'return_url' => $meta['return_url'] ?? config('app.url').'/wallet',
                'api_sig' => $sig,
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) (data_get($response, 'redirect_url')
                ?? data_get($response, 'transaction.redirect_url', '')),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $notifySig = (string) $request->input('notify_sig', '');
        if ($notifySig === '') {
            return false;
        }

        $expected = md5(implode('|', [
            (string) config('services.payssion.api_key'),
            (string) $request->input('pm_id', ''),
            (string) $request->input('amount', ''),
            (string) $request->input('currency', ''),
            (string) $request->input('order_id', ''),
            (string) $request->input('state', ''),
            (string) config('services.payssion.secret_key'),
        ]));

        return hash_equals($expected, $notifySig);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $success = (string) $request->input('state', '') === 'completed';

        [$userId, $reference] = array_pad(explode(':', (string) $request->input('order_id', ''), 2), 2, null);

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) ($reference ?? ''),
            userId: $userId !== null && $userId !== '' ? (int) $userId : null,
            amount: (float) $request->input('amount', 0),
            currency: strtoupper((string) $request->input('currency', 'USD')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
