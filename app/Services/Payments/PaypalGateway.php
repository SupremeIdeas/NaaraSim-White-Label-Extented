<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayPal (blueprint Section 14.2 — added gateway). Uses the documented Orders v2
 * API: an OAuth2 client-credentials token, then create an order and send the
 * user to the `approve` link. Webhook authenticity is confirmed the PayPal way —
 * POST the transmission headers + body to /v1/notifications/verify-webhook-
 * signature (money-safety rule 9) — never trusted before that returns SUCCESS.
 * When unconfigured every method degrades safely so the platform runs without it.
 */
class PaypalGateway implements DisputeAwareGateway, PaymentGatewayInterface, RefundableGateway
{
    public function name(): string
    {
        return 'paypal';
    }

    /**
     * Refund via POST /v2/payments/captures/{capture_id}/refund (BUILD-2 §7.1).
     * Needs the capture id captured at webhook time. Amount in major units.
     */
    public function refund(string $reference, float $amount, string $currency, array $context = []): RefundResult
    {
        if (! $this->configured()) {
            return RefundResult::fail('PayPal is not configured.');
        }
        $captureId = (string) ($context['provider_charge_id'] ?? '');
        if ($captureId === '') {
            return RefundResult::fail('No PayPal capture id on file to refund.');
        }

        try {
            $res = Http::withToken($this->token())->acceptJson()->timeout(15)->connectTimeout(3)
                ->post($this->base()."/v2/payments/captures/{$captureId}/refund", [
                    'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency_code' => strtoupper($currency)],
                ]);
        } catch (\Throwable $e) {
            return RefundResult::fail('Could not reach PayPal to refund.');
        }

        if (! $res->successful()) {
            return RefundResult::fail((string) (data_get($res->json(), 'message') ?: 'PayPal refused the refund.'));
        }

        return in_array(data_get($res->json(), 'status'), ['COMPLETED', 'PENDING'], true)
            ? RefundResult::ok((string) data_get($res->json(), 'id'))
            : RefundResult::fail('PayPal refund status: '.(string) data_get($res->json(), 'status'));
    }

    /**
     * Dispute events (BUILD-2 §7.2). CUSTOMER.DISPUTE.CREATED opens;
     * CUSTOMER.DISPUTE.RESOLVED carries dispute_outcome.outcome_code
     * (RESOLVED_SELLER_FAVOUR = won, else lost). The disputed capture id maps
     * back to our reference via the captured charge.
     */
    public function parseDispute(Request $request): ?DisputeEvent
    {
        $type = (string) $request->input('event_type');
        if (! in_array($type, ['CUSTOMER.DISPUTE.CREATED', 'CUSTOMER.DISPUTE.RESOLVED'], true)) {
            return null;
        }

        $resource = $request->input('resource', []);
        $status = DisputeEvent::OPEN;
        if ($type === 'CUSTOMER.DISPUTE.RESOLVED') {
            $status = data_get($resource, 'dispute_outcome.outcome_code') === 'RESOLVED_SELLER_FAVOUR'
                ? DisputeEvent::WON : DisputeEvent::LOST;
        }

        return new DisputeEvent(
            gateway: $this->name(),
            providerDisputeId: (string) data_get($resource, 'dispute_id', ''),
            reference: null, // resolved from the capture id below
            amount: (float) data_get($resource, 'dispute_amount.value', 0),
            currency: strtoupper((string) data_get($resource, 'dispute_amount.currency_code', 'USD')),
            status: $status,
            raw: $request->all(),
            providerChargeId: (string) data_get($resource, 'disputed_transactions.0.seller_transaction_id', ''),
        );
    }

    private function configured(): bool
    {
        return ! empty(config('services.paypal.client_id'))
            && ! empty(config('services.paypal.client_secret'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.paypal.base_url', 'https://api-m.paypal.com'), '/');
    }

    /** Short-lived OAuth2 access token (client-credentials). */
    private function token(): string
    {
        return (string) Http::withBasicAuth(
            (string) config('services.paypal.client_id'),
            (string) config('services.paypal.client_secret'),
        )->asForm()->acceptJson()->timeout(15)->connectTimeout(3)
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withToken($this->token())->acceptJson()->timeout(15)->connectTimeout(3)
            ->post($this->base().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    // custom_id carries user + reference so the webhook can credit
                    // the right wallet idempotently (no cost/PII exposed).
                    'custom_id' => $user->id.':'.$reference,
                    'invoice_id' => $reference,
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'user_action' => 'PAY_NOW',
                    'return_url' => $meta['return_url'] ?? config('app.url').'/wallet',
                    'cancel_url' => $meta['cancel_url'] ?? config('app.url').'/wallet',
                ],
            ])->throw()->json();

        $approve = collect($response['links'] ?? [])->firstWhere('rel', 'approve');

        return [
            'reference' => $reference,
            'redirect_url' => (string) ($approve['href'] ?? ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $webhookId = (string) config('services.paypal.webhook_id');
        if ($webhookId === '') {
            return false;
        }

        try {
            $status = Http::withToken($this->token())->acceptJson()->timeout(15)->connectTimeout(3)
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

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $type = (string) $request->input('event_type');
        $resource = $request->input('resource', []);
        $success = in_array($type, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true);

        // custom_id = "<user_id>:<reference>".
        $custom = (string) (data_get($resource, 'custom_id')
            ?? data_get($resource, 'purchase_units.0.custom_id', ''));
        [$userId, $reference] = array_pad(explode(':', $custom, 2), 2, null);

        $amount = (float) (data_get($resource, 'amount.value')
            ?? data_get($resource, 'purchase_units.0.amount.value', 0));
        $currency = strtoupper((string) (data_get($resource, 'amount.currency_code')
            ?? data_get($resource, 'purchase_units.0.amount.currency_code', 'USD')));

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) ($reference ?? data_get($resource, 'invoice_id', '')),
            userId: $userId !== null && $userId !== '' ? (int) $userId : null,
            amount: $amount,
            currency: $currency,
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
            // The capture id — what a refund/dispute cites (PAYMENT.CAPTURE.* resource.id).
            providerChargeId: (string) (data_get($resource, 'id')
                ?? data_get($resource, 'purchase_units.0.payments.captures.0.id', '')),
        );
    }
}
