<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Payouts (NAARA-BUILD-22 §5). Self-service money-out for
 * internationally-earning users who have no Paystack/Flutterwave-compatible bank
 * account — they withdraw straight to a PayPal email. Auth + webhook verification
 * mirror the PayPal collection gateway (client-credentials token + PayPal's
 * verify-webhook-signature API). The synchronous batch response is provisional;
 * the PAYMENT.PAYOUTS-ITEM.* webhook is the source of truth.
 *
 * A PayPal payout account stores the payee email in `account_number` (type
 * 'paypal'); no PSP recipient object is needed, so createRecipient just returns it.
 */
class PayPalPayoutGateway implements PayoutGatewayInterface
{
    public function name(): string
    {
        return 'paypal';
    }

    public function available(): bool
    {
        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.paypal.base_url', 'https://api-m.paypal.com'), '/');
    }

    private function token(): string
    {
        return (string) Http::withBasicAuth(
            (string) config('services.paypal.client_id'),
            (string) config('services.paypal.client_secret'),
        )->asForm()->acceptJson()
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');
    }

    public function createRecipient(PayoutAccount $account): string
    {
        // PayPal pays to an email address — no recipient object to create.
        return (string) $account->account_number;
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        try {
            $response = Http::withToken($this->token())->acceptJson()
                ->post($this->base().'/v1/payments/payouts', [
                    'sender_batch_header' => [
                        'sender_batch_id' => $request->reference,
                        'email_subject' => config('app.name').' payout',
                        'email_message' => 'Your '.config('app.name').' earnings payout.',
                    ],
                    'items' => [[
                        'recipient_type' => 'EMAIL',
                        'receiver' => (string) $account->account_number,
                        'sender_item_id' => $request->reference,
                        'amount' => [
                            'value' => number_format((float) $request->amount, 2, '.', ''),
                            'currency' => strtoupper($account->currency ?: 'USD'),
                        ],
                    ]],
                ])->json();
        } catch (\Throwable $e) {
            // Infra error (network/5xx) — let the engine retry.
            throw $e;
        }

        $status = (string) data_get($response, 'batch_header.batch_status', '');
        if ($status === '' || in_array($status, ['DENIED', 'CANCELED'], true)) {
            return new PayoutTransferResult(
                status: 'failed',
                failureReason: (string) data_get($response, 'message', 'PayPal rejected the payout.'),
            );
        }

        // PENDING / PROCESSING / SUCCESS — the per-item webhook confirms settlement.
        return new PayoutTransferResult(
            status: 'processing',
            providerRef: (string) data_get($response, 'batch_header.payout_batch_id', '') ?: null,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookId = (string) config('services.paypal.webhook_id');
        if ($webhookId === '' || ! $this->available()) {
            return false;
        }

        try {
            $status = Http::withToken($this->token())->acceptJson()
                ->post($this->base().'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $request->header('paypal-auth-algo'),
                    'cert_url' => $request->header('paypal-cert-url'),
                    'transmission_id' => $request->header('paypal-transmission-id'),
                    'transmission_sig' => $request->header('paypal-transmission-sig'),
                    'transmission_time' => $request->header('paypal-transmission-time'),
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->json()->all(),
                ])->throw()->json('verification_status');

            return $status === 'SUCCESS';
        } catch (\Throwable) {
            return false;
        }
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        $type = (string) $request->input('event_type');
        if (! str_starts_with($type, 'PAYMENT.PAYOUTS-ITEM.')) {
            return null;
        }

        $resource = $request->input('resource', []);
        // sender_item_id echoes our payout reference.
        $reference = (string) data_get($resource, 'payout_item.sender_item_id', '');

        return new PayoutEvent(
            provider: $this->name(),
            reference: $reference,
            status: match ($type) {
                'PAYMENT.PAYOUTS-ITEM.SUCCEEDED' => 'paid',
                'PAYMENT.PAYOUTS-ITEM.REFUNDED', 'PAYMENT.PAYOUTS-ITEM.RETURNED', 'PAYMENT.PAYOUTS-ITEM.REVERSED' => 'reversed',
                default => 'failed', // FAILED / BLOCKED / DENIED / CANCELED / UNCLAIMED
            },
            providerRef: (string) data_get($resource, 'payout_item_id', '') ?: null,
        );
    }
}
