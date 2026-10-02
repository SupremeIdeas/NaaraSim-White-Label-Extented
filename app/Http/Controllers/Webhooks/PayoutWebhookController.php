<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PayoutWebhookEvent;
use App\Models\WebhookLog;
use App\Services\Payouts\PayoutEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Payout webhooks for Paystack / Flutterwave transfers (ROADMAP §Layer 0.2 —
 * mirrors the payment webhook discipline). Verify the signature BEFORE touching
 * the payload, log it, then apply the final state to the payout_request through
 * PayoutService (idempotent). A failed verification is logged and rejected 401.
 */
class PayoutWebhookController extends Controller
{
    private const PROVIDERS = ['paystack', 'flutterwave', 'paypal', 'cryptomus', 'stripe'];

    /**
     * The provider's own event id when it sends one (Stripe); otherwise a stable hash of the facts
     * that identify the event, so the same notification delivered twice maps to the same row.
     */
    private function eventId(string $provider, Request $request, ?PayoutEvent $event): string
    {
        $native = $provider === 'stripe' ? $request->input('id') : null;
        if (is_string($native) && $native !== '') {
            return $native;
        }

        return hash('sha256', implode('|', [
            $provider, $event?->reference, $event?->status, $event?->providerRef,
            (string) ($request->input('event') ?? $request->input('type')),
            (string) data_get($request->all(), 'data.id', ''),
        ]));
    }

    public function __invoke(Request $request, string $provider, PayoutService $payouts): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true) && ! \App\Services\Payouts\Extensions\PayoutRailExtensions::installed($provider)) {
            throw new NotFoundHttpException;
        }

        /** @var PayoutGatewayInterface $gateway */
        $gateway = app("payout.{$provider}");
        $verified = $gateway->verifyWebhook($request);

        $log = WebhookLog::create([
            'provider' => 'payout:'.$provider,
            'event_type' => $request->input('event') ?? $request->input('type'),
            'payload' => $request->all(),
            'signature' => $request->header('x-paystack-signature') ?? $request->header('verif-hash') ?? $request->header('Stripe-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $event = $gateway->parseWebhook($request);
        $eventId = $this->eventId($provider, $request, $event);

        // Duplicate delivery → 200 and nothing else (Addendum D-3.3). The unique index is the lock.
        try {
            $row = PayoutWebhookEvent::create([
                'provider' => $provider,
                'provider_event_id' => $eventId,
                'request_reference' => $event?->reference ?: null,
                'event_type' => (string) ($request->input('event') ?? $request->input('type')),
                'received_at' => now(),
                'payload_hash' => hash('sha256', $request->getContent()),
                'raw_payload' => $request->getContent(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $log->update(['processed' => true, 'processed_at' => now()]);

            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $outcome = 'ignored';
            if ($event !== null && $event->reference !== '') {
                $outcome = $payouts->applyWebhook($event) === null ? 'unknown_reference' : 'applied';
            }
        } catch (\Throwable $e) {
            $row->delete(); // let the provider's retry be processed instead of swallowed as a duplicate
            throw $e;
        }

        $row->update(['processed_at' => now(), 'outcome' => $outcome]);
        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
