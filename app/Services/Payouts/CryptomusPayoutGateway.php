<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Cryptomus Payout (NAARA-BUILD-22 §5). A real crypto payout rail for users who
 * think about money in crypto — chosen over NOWPayments because its payout API
 * is a single signed endpoint (no JWT + interactive 2FA handshake). A crypto
 * payout account stores the destination wallet in `account_number`, the coin in
 * `currency` (e.g. USDT), and the network in `bank_code` (e.g. TRON).
 *
 * Signing mirrors the Cryptomus collection gateway: sign = md5(base64(json) .
 * payout_api_key) — a SEPARATE payout key from the collection api_key. The
 * synchronous response is provisional; the signed webhook confirms settlement
 * (money-safety rule 9: verify BEFORE trusting the payload).
 */
class CryptomusPayoutGateway implements PayoutGatewayInterface
{
    public function name(): string
    {
        return 'cryptomus';
    }

    public function available(): bool
    {
        return filled(config('services.cryptomus.merchant_id'))
            && filled(config('services.cryptomus.payout_api_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.cryptomus.base_url', 'https://api.cryptomus.com'), '/');
    }

    private function payoutKey(): string
    {
        return (string) config('services.cryptomus.payout_api_key');
    }

    /** md5(base64(json_body) . payout_api_key) — Cryptomus's documented scheme. */
    private function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return md5(base64_encode($json).$this->payoutKey());
    }

    public function createRecipient(PayoutAccount $account): string
    {
        // No recipient object — the destination is the wallet address itself.
        return (string) $account->account_number;
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        $payload = [
            'amount' => number_format((float) $request->amount, 8, '.', ''),
            'currency' => strtoupper($account->currency ?: 'USDT'),
            'network' => strtoupper((string) $account->bank_code) ?: 'TRON',
            'order_id' => $request->reference,
            'address' => (string) $account->account_number,
            'is_subtract' => '1', // fee taken from the amount, never added on top
            'url_callback' => route('webhooks.payouts', ['provider' => $this->name()]),
        ];

        $response = Http::withHeaders([
            'merchant' => (string) config('services.cryptomus.merchant_id'),
            'sign' => $this->sign($payload),
        ])->acceptJson()->post($this->base().'/v1/payout', $payload)->json();

        // Cryptomus returns state=0 on success; anything else is a rejection.
        if ((int) data_get($response, 'state', -1) !== 0) {
            return new PayoutTransferResult(
                status: 'failed',
                failureReason: (string) data_get($response, 'message', data_get($response, 'errors.0', 'Cryptomus rejected the payout.')),
            );
        }

        $status = (string) data_get($response, 'result.status', 'process');

        return new PayoutTransferResult(
            status: match ($status) {
                'paid', 'paid_over' => 'paid',
                'fail', 'cancel', 'system_fail' => 'failed',
                default => 'processing', // process / check / confirm_check — webhook confirms
            },
            providerRef: (string) data_get($response, 'result.uuid', '') ?: null,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $data = $request->all();
        $sign = (string) ($data['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        unset($data['sign']);

        return hash_equals($this->sign($data), $sign);
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        // Only payout webhooks (Cryptomus flags these with type=payout).
        if ($request->input('type') !== null && $request->input('type') !== 'payout') {
            return null;
        }

        $status = (string) $request->input('status', '');

        return new PayoutEvent(
            provider: $this->name(),
            reference: (string) $request->input('order_id', ''),
            status: match ($status) {
                'paid', 'paid_over' => 'paid',
                'cancel', 'system_fail', 'fail' => 'reversed',
                default => 'failed',
            },
            providerRef: (string) $request->input('uuid', '') ?: null,
        );
    }
}
