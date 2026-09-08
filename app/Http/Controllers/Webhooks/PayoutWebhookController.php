<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
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

    public function __invoke(Request $request, string $provider, PayoutService $payouts): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
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
        if ($event !== null && $event->reference !== '') {
            $payouts->applyWebhook($event);
        }

        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
