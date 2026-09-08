<?php

namespace App\Services\Brands;

use App\Models\BrandPriorityLog;
use App\Models\BrandSubscription;
use App\Models\SocialFollowClaim;
use Illuminate\Support\Carbon;

/**
 * Follower-guarantee priority-boost (BUILD-9 §6). A real, auditable calculation
 * — not a cosmetic reorder. At the end of each billing cycle, for each of a
 * brand's handles, compare actual follows delivered that month against the
 * plan's guarantee. A shortfall raises the brand's priority_score by the raw
 * shortfall count (shortfall_pct × guaranteed = guaranteed − actual — a simple,
 * explainable formula), boosting its directory placement next cycle. A fully
 * on-target month decays the score back toward 0 by a fixed step, so a boost is
 * a temporary correction, never a permanent advantage. Every adjustment is
 * logged to brand_priority_log so placement is always explainable.
 */
class BrandPriorityService
{
    /** Fixed decay applied each on-target month (§6.5). */
    private const DECAY_STEP = 10;

    public function evaluate(BrandSubscription $sub, Carbon $periodStart, Carbon $periodEnd): void
    {
        $brand = $sub->brandPartner;
        $plan = $sub->plan;
        if (! $brand || ! $plan || ! $brand->isSelfService()) {
            return;
        }

        $guaranteed = (int) $plan->guaranteed_followers_per_handle_per_month;
        $month = $periodEnd->format('Y-m');
        $handles = $brand->handles()->where('is_active', true)->get();

        // Per-handle actual follows delivered this month.
        $perHandle = [];
        $totalShortfall = 0;
        foreach ($handles as $handle) {
            $actual = SocialFollowClaim::where('brand_partner_handle_id', $handle->id)
                ->whereBetween('claimed_at', [$periodStart, $periodEnd])->count();
            $adjustment = ($guaranteed > 0 && $actual < $guaranteed) ? ($guaranteed - $actual) : 0;
            $totalShortfall += $adjustment;
            $perHandle[] = ['handle' => $handle, 'actual' => $actual, 'adjustment' => $adjustment];
        }

        // Apply the net change, then log with the resulting score.
        if ($totalShortfall > 0) {
            $newScore = (int) $brand->priority_score + $totalShortfall;
        } else {
            $newScore = max(0, (int) $brand->priority_score - self::DECAY_STEP);
        }
        $brand->forceFill(['priority_score' => $newScore])->save();

        if ($perHandle === []) {
            // No handles to evaluate — still log the decay so history is complete.
            BrandPriorityLog::create([
                'brand_partner_id' => $brand->id, 'handle_id' => null, 'month' => $month,
                'guaranteed' => $guaranteed, 'actual' => 0,
                'adjustment' => $newScore - (int) $brand->getOriginal('priority_score'),
                'new_priority_score' => $newScore,
            ]);

            return;
        }

        foreach ($perHandle as $row) {
            BrandPriorityLog::create([
                'brand_partner_id' => $brand->id,
                'handle_id' => $row['handle']->id,
                'month' => $month,
                'guaranteed' => $guaranteed,
                'actual' => $row['actual'],
                'adjustment' => $totalShortfall > 0 ? $row['adjustment'] : -self::DECAY_STEP,
                'new_priority_score' => $newScore,
            ]);
        }
    }
}
