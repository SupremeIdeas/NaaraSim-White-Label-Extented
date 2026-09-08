<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Analytics (Analytics blueprint §7.3) — the deep-dive platform
 * overview: wallet/FX, merchant leaderboard, operational health (KYC/refund/
 * support trends), and provider reliability. The main Dashboard stays fast
 * and glanceable (revenue hero only); this is where an admin drills down —
 * the same "hero summary vs. deep dive" split as Home vs. My Line (Part A),
 * applied to the admin side. Every figure comes from
 * PlatformAnalyticsService — never a duplicate query — so it can never
 * disagree with the Dashboard or any other admin surface.
 */
#[Layout('components.layouts.admin')]
class Analytics extends Component
{
    /** Look-back window in days (7 / 30 / 90) — same convention as Reconciliation. */
    public int $days = 30;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    public function render(PlatformAnalyticsService $analytics)
    {
        $range = "{$this->days}d";

        return view('livewire.admin.analytics', [
            'topUpByGateway' => $analytics->topUpVolumeByGateway($range),
            'topUpByCurrency' => $analytics->topUpVolumeByCurrency($range),
            'usdLiability' => $analytics->platformUsdLiability(),
            'fxRates' => $analytics->fxRateSnapshot(),
            'merchantLeaderboard' => $analytics->merchantVolumeLeaderboard($range),
            'merchantEarnings' => $analytics->merchantEarningsTotal($range),
            'kyc' => $analytics->kycApprovalRate($range),
            'refunds' => $analytics->refundRateVsRevenue($range),
            'support' => $analytics->supportQueueTrend($range),
            'providerReliability' => $analytics->providerReliabilitySummary(),
            'esimUsage' => $analytics->platformEsimUsageSummary(),
        ]);
    }
}
