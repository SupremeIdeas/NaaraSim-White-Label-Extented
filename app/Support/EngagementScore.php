<?php

namespace App\Support;

use App\Models\User;

/**
 * A merchant-readiness engagement score for the admin Users leaderboard
 * (BUILD-4 §4.1). Combines the signals that matter for spotting a good
 * prospective merchant: referral reach, verified-referral quality (referred
 * users who actually transacted), purchase volume, identity verification, and
 * account tenure. Higher = closer to merchant-worthy.
 *
 * Computed in PHP from already-loaded fields so the admin list stays a single
 * query (the caller eager-loads `wallet` + `withCount('referralsMade')`).
 */
class EngagementScore
{
    /**
     * @return array{score: int, referrals: int, verified_referrals: int, spend: float, verified: bool, age_days: int}
     */
    public static function for(User $user): array
    {
        $referrals = (int) ($user->referrals_made_count ?? $user->referralsMade()->count());
        // Verified-referral quality: referred users who have actually spent.
        $verifiedReferrals = (int) ($user->verified_referrals_count
            ?? $user->referralsMade()->whereHas('referred.wallet', fn ($q) => $q->where('total_spent', '>', 0))->count());
        $spend = (float) ($user->wallet?->total_spent ?? 0);
        $verified = in_array($user->kyc_status, ['verified', 'approved', 'level2', 'level3'], true)
            || ($user->kyc_level ?? 0) >= 2;
        $ageDays = (int) $user->created_at?->diffInDays(now());

        $score = 0;
        $score += min($referrals, 50) * 2;              // reach
        $score += min($verifiedReferrals, 50) * 4;       // quality referrals weigh more
        $score += (int) min($spend / 10, 100);           // $10 spent = +1, capped
        $score += $verified ? 20 : 0;                    // identity verified
        $score += (int) min($ageDays / 30, 12);          // tenure, capped at a year

        return [
            'score' => $score,
            'referrals' => $referrals,
            'verified_referrals' => $verifiedReferrals,
            'spend' => round($spend, 2),
            'verified' => $verified,
            'age_days' => $ageDays,
        ];
    }
}
