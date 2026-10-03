<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Models\KycVerification;
use App\Models\PayoutRequest;
use App\Services\Kyc\KycService;
use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Services\Payouts\PayoutAdmission;
use App\Support\PayoutSettings;

/**
 * G4 — KYC and limits. User-initiated buckets only (staff are exempt by policy,
 * partners/platform have their own gates). Counts IN-FLIGHT requests, not just paid.
 */
class KycAndLimitsGate implements Gate
{
    private const LOCAL_RAILS = ['paystack', 'flutterwave'];

    public function __construct(private KycService $kyc) {}

    public function id(): string
    {
        return 'G4_kyc_limits';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $r = $ctx->request;
        if (! in_array($r->source_bucket, PayoutAdmission::USER_BUCKETS, true) || $ctx->user === null) {
            return GateResult::pass($this->id(), ['exempt_bucket' => $r->source_bucket]);
        }

        $user = $ctx->user;
        $usd = $ctx->usd();
        $hasL2 = $this->kyc->hasLevel($user, KycVerification::L2);
        $evidence = ['kyc_l2' => $hasL2, 'usd' => $usd];

        // Free-payout allowance: everyone else's committed requests (this one excluded).
        $others = PayoutRequest::where('user_id', $user->id)->where('id', '!=', $r->id)
            ->whereIn('status', PayoutRequest::COMMITTED)->count();
        if ($others >= PayoutSettings::freePayoutCount() && ! $hasL2) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'kyc_required', $evidence + ['committed_others' => $others]);
        }

        // First payout through a non-local rail needs identity verification.
        if (! in_array($r->provider, self::LOCAL_RAILS, true) && ! $hasL2
            && ! PayoutRequest::where('user_id', $user->id)->where('provider', $r->provider)->where('status', PayoutRequest::PAID)->exists()) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'kyc_required_global', $evidence + ['provider' => $r->provider]);
        }

        if ($usd !== null) {
            // Hard per-user caps: exceeding one is a plain "not now" — funds go back.
            foreach (['daily' => 1, 'weekly' => 7, 'monthly' => 30] as $window => $days) {
                $cap = PayoutSettings::userCapUsd($window);
                if ($cap <= 0) {
                    continue;
                }
                $used = (float) PayoutRequest::where('user_id', $user->id)->where('id', '!=', $r->id)
                    ->whereIn('status', PayoutRequest::COMMITTED)->where('created_at', '>=', now()->subDays($days))
                    ->sum('usd_amount');
                if ($used + $usd > $cap) {
                    return GateResult::fail($this->id(), GateResult::REJECT, 'cap_exceeded', $evidence + ['window' => $window, 'cap' => $cap, 'used' => $used]);
                }
            }

            // A sudden jump versus anything they have ever cashed out needs KYC-L2.
            $largest = (float) PayoutRequest::where('user_id', $user->id)->where('status', PayoutRequest::PAID)->max('usd_amount');
            if ($largest > 0 && $usd > $largest * PayoutSettings::largestPreviousMultiple() && ! $hasL2) {
                return GateResult::fail($this->id(), GateResult::HOLD, 'amount_spike', $evidence + ['largest_previous' => $largest]);
            }
        }

        return GateResult::pass($this->id(), $evidence);
    }
}
