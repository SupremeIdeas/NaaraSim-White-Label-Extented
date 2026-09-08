<?php

namespace App\Livewire;

use App\Services\Analytics\ConnectivityAnalyticsService;
use App\Support\ConnectivityHub;
use App\Support\MarketingCoupons;
use App\Support\NaaraFacts;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home dashboard. The full "My Connectivity" management hub now lives on its own
 * dedicated My Lines page (route numbers.lines); the dashboard keeps only a slim
 * at-a-glance summary of it plus the greeting/showcase/wallet. Both read the same
 * ConnectivityHub source so the summary and the full page never disagree.
 */
#[Layout('components.layouts.customer')]
class Dashboard extends Component
{
    public function render(ConnectivityAnalyticsService $analytics)
    {
        $user = auth()->user();
        $hub = ConnectivityHub::for($user);

        return view('livewire.dashboard', [
            'esimActiveCount' => $hub['esimActiveCount'],
            'numberActiveCount' => $hub['numberActiveCount'],
            'archivedCount' => $hub['archivedCount'],
            'hasAny' => $hub['hasAny'],
            'wallet' => $user->wallet,
            // Greeting + fact-of-the-day (owner request): welcome by name and
            // teach the worth of what a NaaraSim number/eSIM can do globally.
            'greeting' => NaaraFacts::greeting($user),
            'greetingAsk' => NaaraFacts::askOfTheDay($user),
            'factOfTheDay' => NaaraFacts::dailyFor($user),
            // Friendly coupon nudge for a not-yet-purchased account (owner
            // request) — null when off / already bought / no live code.
            'couponNudge' => MarketingCoupons::nudgeFor($user),
        ] + $this->analyticsHero($user, $hub['hasAny'], $analytics));
    }

    /**
     * Connectivity Analytics blueprint Part A §2.5 — the light-touch Home
     * hero: dependency-free SVG sparklines only (Chart.js is reserved for My
     * Line's richer charts), so this stays cheap on every dashboard load.
     * Skipped entirely for an account with no lines yet — nothing real to show.
     */
    private function analyticsHero($user, bool $hasAny, ConnectivityAnalyticsService $analytics): array
    {
        if (! $hasAny) {
            return ['showAnalyticsHero' => false];
        }

        $usage = $analytics->weeklyDataUsage($user, 7);
        $usageSparkline = $this->sparkline(collect($usage['daily'])->pluck('mb'));

        $since = now()->subDays(29)->startOfDay();
        $flows = $user->walletTransactions()
            ->where('created_at', '>=', $since)
            ->get(['type', 'amount', 'currency', 'created_at']);

        $dayKeys = collect(range(29, 0))->map(fn ($back) => now()->subDays($back)->toDateString());
        $deposits = $dayKeys->map(fn ($day) => (float) $flows
            ->filter(fn ($t) => $t->type === 'credit' && $t->created_at->toDateString() === $day)
            ->sum('amount'));
        $spend = $dayKeys->map(fn ($day) => (float) $flows
            ->filter(fn ($t) => $t->type === 'debit' && $t->currency === 'USD' && $t->created_at->toDateString() === $day)
            ->sum('amount'));

        // Both lines share one peak so deposits and spend stay comparable on
        // the same axis — never independently rescaled.
        $flowPeak = max((float) $deposits->max(), (float) $spend->max(), 0.01);

        return [
            'showAnalyticsHero' => true,
            'weeklyUsageMb' => $usage['total_mb'],
            'usageSparkline' => $usageSparkline,
            'hasUsageData' => $usage['total_mb'] > 0,
            'depositsSparkline' => $this->sparkline($deposits, $flowPeak),
            'spendSparkline' => $this->sparkline($spend, $flowPeak),
            'hasFlowData' => $deposits->sum() > 0 || $spend->sum() > 0,
        ];
    }

    /** Normalise a series into SVG polyline points (viewBox 0 0 200 48, baseline y=44) — the same convention as Wallet.php's spend sparkline. */
    private function sparkline(Collection $values, ?float $peak = null): string
    {
        $peak ??= max((float) $values->max(), 0.01);
        $last = max($values->count() - 1, 1);

        return $values->map(
            fn ($v, $i) => round($i * (200 / $last), 1).','.round(44 - ((float) $v / $peak) * 36, 1)
        )->implode(' ');
    }
}
