<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Stripe (blueprint Section 14.2). Webhook verification follows Stripe's
 * scheme: the Stripe-Signature header carries `t=<ts>,v1=<sig>` where sig =
 * HMAC-SHA256 of "<ts>.<raw body>" with the endpoint's webhook secret, checked
 * constant-time within a timestamp tolerance. Amounts are in minor units.
 */
class StripeGateway implements DisputeAwareGateway, PaymentGatewayInterface, RefundableGateway
{
    private const TOLERANCE_SECONDS = 300;

    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Refund via POST /v1/refunds on the PaymentIntent captured at webhook time
     * (BUILD-2 §7.1). Amount in minor units. Bounded timeout. Accepts a
     * succeeded/pending refund; anything else is a safe failure.
     */
    public function refund(string $reference, float $amount, string $currency, array $context = []): RefundResult
    {
        $secret = (string) config('services.stripe.secret_key');
        if ($secret === '') {
            return RefundResult::fail('Stripe is not configured.');
        }
        $pi = (string) ($context['provider_charge_id'] ?? '');
        if ($pi === '') {
            return RefundResult::fail('No Stripe payment reference on file to refund.');
        }

        try {
            $res = Http::withToken($secret)->asForm()->timeout(15)->connectTimeout(3)
                ->post(rtrim((string) config('services.stripe.base_url'), '/').'/refunds', [
                    'payment_intent' => $pi,
                    'amount' => (int) round($amount * 100),
                ]);
        } catch (\Throwable $e) {
            return RefundResult::fail('Could not reach Stripe to refund.');
        }

        if (! $res->successful()) {
            return RefundResult::fail((string) (data_get($res->json(), 'error.message') ?: 'Stripe refused the refund.'));
        }

        return in_array(data_get($res->json(), 'status'), ['succeeded', 'pending'], true)
            ? RefundResult::ok((string) data_get($res->json(), 'id'))
            : RefundResult::fail('Stripe refund status: '.(string) data_get($res->json(), 'status'));
    }

    /**
     * Dispute events (BUILD-2 §7.2). charge.dispute.created opens; charge.dispute
     * .closed resolves with data.object.status = won | lost. The dispute cites a
     * payment_intent, mapped back to our reference via the captured charge.
     */
    public function parseDispute(Request $request): ?DisputeEvent
    {
        $type = (string) $request->input('type');
        if (! in_array($type, ['charge.dispute.created', 'charge.dispute.closed'], true)) {
            return null;
        }

        $object = $request->input('data.object', []);
        $status = DisputeEvent::OPEN;
        if ($type === 'charge.dispute.closed') {
            $status = data_get($object, 'status') === 'won' ? DisputeEvent::WON : DisputeEvent::LOST;
        }

        return new DisputeEvent(
            gateway: $this->name(),
            providerDisputeId: (string) data_get($object, 'id', ''),
            reference: null, // resolved from the payment_intent below
            amount: (float) data_get($object, 'amount', 0) / 100,
            currency: strtoupper((string) data_get($object, 'currency', 'usd')),
            status: $status,
            raw: $request->all(),
            providerChargeId: (string) data_get($object, 'payment_intent', ''),
        );
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withToken(config('services.stripe.secret_key'))
            ->asForm()
            ->post(rtrim(config('services.stripe.base_url'), '/').'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $reference,
                'success_url' => $meta['success_url'] ?? config('app.url').'/wallet',
                'cancel_url' => $meta['cancel_url'] ?? config('app.url').'/wallet',
                'metadata' => ['user_id' => $user->id],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => (int) round($amount * 100),
                        'product_data' => ['name' => 'NaaraSim wallet top-up'],
                    ],
                ]],
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) ($response['url'] ?? ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');
        if (! is_string($header) || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', $piece, 2), 2, null);
            $parts[trim((string) $k)] = trim((string) $v);
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $object = $request->input('data.object', []);
        $type = $request->input('type');
        $success = in_array($type, ['checkout.session.completed', 'payment_intent.succeeded'], true)
            && in_array(data_get($object, 'payment_status', 'paid'), ['paid', 'succeeded', null], true);

        $amount = (float) (data_get($object, 'amount_total') ?? data_get($object, 'amount', 0)) / 100;

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) (data_get($object, 'client_reference_id') ?? data_get($object, 'id', '')),
            userId: ($uid = data_get($object, 'metadata.user_id')) !== null ? (int) $uid : null,
            amount: $amount,
            currency: strtoupper((string) data_get($object, 'currency', 'usd')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
            // The PaymentIntent is what a refund/dispute cites.
            providerChargeId: (string) (data_get($object, 'payment_intent') ?? data_get($object, 'id', '')),
        );
    }
}
