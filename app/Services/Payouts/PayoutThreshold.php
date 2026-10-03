<?php

namespace App\Services\Payouts;

use App\Models\KycVerification;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Support\PayoutSettings;

/**
 * The unified free-payout / KYC threshold (NAARA-BUILD-22 §3). A user may take up
 * to `payouts.free_payout_count` successful payouts with NO identity verification;
 * once they reach that limit, KYC-L2 is required on that and every later payout.
 *
 * The counter is per USER and COMBINED across every earner type (partner,
 * merchant, referral) — it counts settled PayoutRequests, so there is exactly one
 * source of truth and no per-bucket divergence. Browsing the dashboard/balance is
 * never gated; only the act of withdrawing past the free threshold prompts KYC.
 */
class PayoutThreshold
{
    public function __construct(private readonly KycService $kyc) {}

    /** Successful payouts this user has already taken (all earner types). Display only. */
    public function payoutCount(User $user): int
    {
        return PayoutRequest::where('user_id', $user->id)
            ->where('status', PayoutRequest::PAID)
            ->count();
    }

    /**
     * Payouts the user has COMMITTED to: paid + every one still in flight (pending,
     * approved, processing, awaiting funds). Counting only `paid` let a user on a
     * slow rail (up to 14 days) file many requests before any settled and exceed the
     * free limit. A reversed/failed request frees its slot automatically.
     */
    public function committedCount(User $user): int
    {
        return PayoutRequest::where('user_id', $user->id)
            ->whereIn('status', PayoutRequest::COMMITTED)
            ->count();
    }

    public function remainingFree(User $user): int
    {
        return max(0, PayoutSettings::freePayoutCount() - $this->committedCount($user));
    }

    /** True once the user has spent their free payouts — KYC-L2 now required. */
    public function requiresKyc(User $user): bool
    {
        return $this->committedCount($user) >= PayoutSettings::freePayoutCount();
    }

    /** True when the user may withdraw right now (free allowance left, or verified). */
    public function canWithdraw(User $user): bool
    {
        return ! $this->requiresKyc($user) || $this->kyc->hasLevel($user, KycVerification::L2);
    }
}
