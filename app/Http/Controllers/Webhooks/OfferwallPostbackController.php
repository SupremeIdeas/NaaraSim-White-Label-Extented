<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AdRewardView;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\CreditSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rewarded-ad / offerwall server-to-server postback (loyalty module). The ad
 * network calls THIS endpoint after a user genuinely completes a view/offer;
 * only then are credits granted. This is the compliant, fraud-proof design —
 * the browser never grants its own reward.
 *
 * Security:
 *   - HMAC-verified with the shared postback secret (hash_equals, constant-time).
 *   - Idempotent on the network's transaction id (unique) — a replayed postback
 *     can never double-credit.
 *   - Optional per-user daily cap so a compromised feed can't drain the economy.
 *
 * Expected signature: hmac_sha256("{user}:{amount}:{txn}", secret).
 */
class OfferwallPostbackController extends Controller
{
    public function __invoke(Request $request, CreditService $credits): JsonResponse
    {
        $secret = (string) config('services.offerwall.postback_secret');
        if ($secret === '' || ! CreditSettings::get('ads_enabled', false)) {
            return response()->json(['ok' => false, 'error' => 'disabled'], 403);
        }

        $userId = (string) $request->input('user', '');
        $amount = (float) $request->input('amount', 0);      // credits to grant
        $txn = (string) $request->input('txn', '');
        $signature = (string) $request->input('signature', '');
        $payoutUsd = $request->has('payout') ? (float) $request->input('payout') : null;

        if ($userId === '' || $txn === '' || $amount <= 0) {
            return response()->json(['ok' => false, 'error' => 'bad request'], 400);
        }

        $expected = hash_hmac('sha256', "{$userId}:{$amount}:{$txn}", $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $user = User::find((int) $userId);
        if ($user === null) {
            return response()->json(['ok' => false, 'error' => 'unknown user'], 404);
        }

        // Idempotency: the network's txn id is unique. A duplicate postback is a
        // success no-op (return ok so the network stops retrying).
        if (AdRewardView::where('external_txn_id', $txn)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Per-user daily cap (defence-in-depth against a bad/compromised feed).
        $cap = (int) CreditSettings::get('ad_daily_cap', 20);
        if ($cap > 0) {
            $todayCredited = (float) AdRewardView::where('user_id', $user->id)
                ->where('status', 'credited')
                ->whereDate('created_at', now()->toDateString())
                ->sum('credits');
            if ($todayCredited + $amount > $cap) {
                AdRewardView::create([
                    'user_id' => $user->id, 'provider' => CreditSettings::get('ad_provider', 'offerwall'),
                    'external_txn_id' => $txn, 'credits' => $amount, 'payout_usd' => $payoutUsd,
                    'status' => 'rejected', 'ip' => $request->ip(),
                ]);

                return response()->json(['ok' => true, 'capped' => true]);
            }
        }

        AdRewardView::create([
            'user_id' => $user->id, 'provider' => CreditSettings::get('ad_provider', 'offerwall'),
            'external_txn_id' => $txn, 'credits' => $amount, 'payout_usd' => $payoutUsd,
            'status' => 'credited', 'ip' => $request->ip(),
        ]);

        $credits->earn($user, $amount, 'ad_reward', 'ad:'.$txn, 'Reward for watching an ad');

        return response()->json(['ok' => true]);
    }
}
