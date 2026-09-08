<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Services\Payouts\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe Connect account webhooks (ROADMAP §Layer 0.2 — Stripe payout rail).
 * Configured as its OWN endpoint in the Stripe dashboard (separate from the
 * payments and payout-transfer webhooks) because it carries a different event
 * family — account.updated, the onboarding-status changes for a connected
 * Express account — signed with its own `connect_webhook_secret`, verified
 * with the same timestamp + HMAC-SHA256 scheme Stripe uses everywhere else.
 */
class StripeConnectWebhookController extends Controller
{
    private const TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, StripeConnectService $connect): JsonResponse
    {
        $verified = $this->verify($request);

        $log = WebhookLog::create([
            'provider' => 'stripe-connect',
            'event_type' => $request->input('type'),
            'payload' => $request->all(),
            'signature' => $request->header('Stripe-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        if ((string) $request->input('type') === 'account.updated') {
            $object = $request->input('data.object', []);
            $accountId = (string) ($object['id'] ?? '');
            $account = $accountId !== '' ? $connect->findByAccountId($accountId) : null;
            if ($account !== null) {
                $connect->applyStatus($account, $object);
            }
        }

        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function verify(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.connect_webhook_secret');
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
}
