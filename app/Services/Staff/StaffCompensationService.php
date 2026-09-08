<?php

namespace App\Services\Staff;

use App\Models\Partner;
use App\Models\StaffCompensationProfile;
use App\Models\User;
use App\Services\Partners\PlatformProfitService;
use Illuminate\Support\Carbon;

/**
 * Staff profit-share accrual (NAARA-BUILD-23 §3). Shares from the SAME
 * PlatformProfitService figure partners share in — never a second profit
 * calculation. The month's profit is computed ONCE and each staff member's own
 * percentage applied to that single figure (mirroring PartnerPayoutService), so a
 * roomful of staff never re-derives the number.
 */
class StaffCompensationService
{
    public function __construct(
        private PlatformProfitService $profit,
        private StaffEarningsService $earnings,
    ) {}

    /**
     * Accrue the previous (fully-closed) month's profit-share for every active
     * staff member. Idempotent per profile+period; floors each share at zero on a
     * negative-profit month. Only a FULLY closed prior month is ever paid.
     *
     * @return array{period:string, profit:float, credited:int, total:float}
     */
    public function closeMonth(?Carbon $anchor = null): array
    {
        // Default to the month before "now" — only a completed month is paid.
        $anchor = ($anchor ?? now())->copy()->subMonthNoOverflow();
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();
        $period = $start->format('Y-m');

        // Compute the platform profit for the period exactly ONCE.
        $profit = $this->profit->profitForPeriod($start, $end);

        $credited = 0;
        $total = 0.0;

        StaffCompensationProfile::where('is_active', true)
            ->whereDate('effective_from', '<=', $start->toDateString())
            ->with('user')
            ->chunkById(200, function ($profiles) use ($profit, $start, $end, $period, &$credited, &$total) {
                foreach ($profiles as $profile) {
                    $user = $profile->user;
                    if (! $user) {
                        continue;
                    }
                    // Floor at zero: a heavily-refunded (negative) month pays nothing.
                    $share = round(max(0.0, $profit) * ((float) $profile->profit_share_pct / 100), 4);
                    if ($share <= 0) {
                        continue;
                    }
                    $earning = $this->earnings->accrue(
                        $user, $share,
                        "staff_share:{$profile->id}:{$period}",
                        $start, $end,
                    );
                    if ($earning) {
                        $credited++;
                        $total += $share;
                    }
                }
            });

        return ['period' => $period, 'profit' => round($profit, 4), 'credited' => $credited, 'total' => round($total, 4)];
    }

    /**
     * The combined percentage of platform profit currently committed across every
     * ACTIVE partner and staff compensation profile (§2 over-commitment safeguard).
     */
    public function combinedCommittedPct(?float $includingStaffPct = null, ?int $excludeStaffProfileId = null): float
    {
        $partners = (float) Partner::where('status', Partner::ACTIVE)->sum('profit_share_pct');

        $staffQuery = StaffCompensationProfile::where('is_active', true);
        if ($excludeStaffProfileId !== null) {
            $staffQuery->where('id', '!=', $excludeStaffProfileId);
        }
        $staff = (float) $staffQuery->sum('profit_share_pct');

        return round($partners + $staff + (float) ($includingStaffPct ?? 0), 3);
    }

    /**
     * Live month-to-date PROJECTED earnings for a staff member (§2.3) — an
     * estimate on profit-so-far, explicitly NOT a payable balance.
     */
    public function projectedThisMonth(StaffCompensationProfile $profile): float
    {
        if (! $profile->is_active) {
            return 0.0;
        }
        $profit = $this->profit->profitForPeriod(now()->startOfMonth(), now());

        return round(max(0.0, $profit) * ((float) $profile->profit_share_pct / 100), 2);
    }
}
