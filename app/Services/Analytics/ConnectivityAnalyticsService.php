<?php

namespace App\Services\Analytics;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\EsimUsageSnapshot;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\CountryNames;
use App\Support\EsimRegions;
use App\Support\ProviderModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Connectivity Analytics blueprint Part A §2.4 — the read-only aggregation
 * layer for Home + My Line. Sibling in spirit to ConnectivityHub: one source
 * of truth, chart-ready return shapes, no duplicate logic in Livewire.
 *
 * Every method is scoped to the given user (or one order they own) and never
 * touches wholesale_cost/provider — spend/plan-mix breakdowns group by the
 * public ProviderModels key or plan country/region, never the raw supplier
 * (money-safety rule 1.2 / repo-wide provider-masking invariant).
 */
class ConnectivityAnalyticsService
{
    /**
     * The usage-over-time series for one eSIM order, shaped for a chart:
     * [{t: ISO8601, used_mb, remaining_mb}]. Reads only esim_usage_snapshots —
     * never calls a provider synchronously (blueprint §2.7 guardrail).
     */
    public function usageTimeline(EsimOrder $order, string $range = '7d'): array
    {
        return EsimUsageSnapshot::where('esim_order_id', $order->id)
            ->where('captured_at', '>=', $this->since($range))
            ->orderBy('captured_at')
            ->get()
            ->map(fn (EsimUsageSnapshot $s) => [
                't' => $s->captured_at->toIso8601String(),
                'used_mb' => $s->data_used_mb,
                'remaining_mb' => $s->data_remaining_mb,
            ])
            ->all();
    }

    /**
     * "Days of data left at current pace" — linear regression over the
     * snapshot history's consumption deltas. Pure arithmetic on real
     * consumption, never invented. Null when there isn't enough history
     * (fewer than 2 snapshots, or usage isn't declining) to project from.
     *
     * @return array{mb_per_day: float, days_left: ?float}|null
     */
    public function usageBurnRate(EsimOrder $order): ?array
    {
        $snapshots = EsimUsageSnapshot::where('esim_order_id', $order->id)
            ->whereNotNull('data_remaining_mb')
            ->orderBy('captured_at')
            ->get(['data_remaining_mb', 'captured_at']);

        if ($snapshots->count() < 2) {
            return null;
        }

        $first = $snapshots->first();
        $last = $snapshots->last();
        $hours = $first->captured_at->diffInHours($last->captured_at, true);
        if ($hours <= 0) {
            return null;
        }

        $consumed = (int) $first->data_remaining_mb - (int) $last->data_remaining_mb;
        if ($consumed <= 0) {
            return ['mb_per_day' => 0.0, 'days_left' => null]; // flat/refilled — no meaningful burn rate
        }

        $mbPerDay = $consumed / ($hours / 24);
        $daysLeft = $mbPerDay > 0 ? round((int) $last->data_remaining_mb / $mbPerDay, 1) : null;

        return ['mb_per_day' => round($mbPerDay, 1), 'days_left' => $daysLeft];
    }

    /**
     * Real data used per day over the trailing window, summed across every
     * eSIM order the user has snapshots for. data_used_mb is cumulative SINCE
     * BUNDLE ACTIVATION (not a per-day figure — CaptureEsimUsageSnapshotJob),
     * so each day's figure is a snapshot-to-snapshot DELTA per order, never
     * the raw column. A bundle refill/reset (delta <= 0) contributes nothing
     * for that step rather than an invented negative — mirrors
     * usageBurnRate()'s arithmetic-only philosophy.
     *
     * @return array{total_mb: float, daily: array<int, array{date: string, mb: float}>}
     */
    public function weeklyDataUsage(User $user, int $days = 7): array
    {
        $since = now()->subDays($days)->startOfDay();

        $snapshots = EsimUsageSnapshot::where('user_id', $user->id)
            ->where('captured_at', '>=', $since->copy()->subDay())
            ->orderBy('captured_at')
            ->get(['esim_order_id', 'data_used_mb', 'captured_at']);

        $dailyTotals = collect(range($days - 1, 0))->mapWithKeys(
            fn ($back) => [now()->subDays($back)->toDateString() => 0.0]
        );

        foreach ($snapshots->groupBy('esim_order_id') as $orderSnapshots) {
            $previous = null;
            foreach ($orderSnapshots as $snapshot) {
                if ($previous !== null) {
                    $delta = (int) $snapshot->data_used_mb - (int) $previous->data_used_mb;
                    $day = $snapshot->captured_at->toDateString();
                    if ($delta > 0 && $dailyTotals->has($day)) {
                        $dailyTotals[$day] += $delta;
                    }
                }
                $previous = $snapshot;
            }
        }

        return [
            'total_mb' => round($dailyTotals->sum(), 1),
            'daily' => $dailyTotals->map(fn ($mb, $date) => ['date' => $date, 'mb' => round($mb, 1)])->values()->all(),
        ];
    }

    /**
     * Spend grouped by public Model (Naara Data / Naara Connect / Naara Verify
     * / Naara Line), read directly from the source-of-truth order tables — not
     * a wallet_transactions reference-string match, so it can never drift from
     * what a user was actually charged (esim_orders.price_charged,
     * sms_orders.charged_to_user).
     *
     * @return array<int, array{model: string, label: string, total: float}>
     */
    public function walletSpendBreakdown(User $user, string $range = '30d'): array
    {
        $since = $this->since($range);

        $esimByVoice = EsimOrder::query()
            ->join('esim_plans', 'esim_plans.id', '=', 'esim_orders.plan_id')
            ->where('esim_orders.user_id', $user->id)
            ->where('esim_orders.created_at', '>=', $since)
            ->whereNotIn('esim_orders.status', ['failed', 'cancelled'])
            ->selectRaw('esim_plans.has_voice as has_voice, sum(esim_orders.price_charged) as total')
            ->groupBy('esim_plans.has_voice')
            ->get();

        $numbersByType = SmsOrder::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->where('status', '!=', 'cancelled')
            ->get(['type', 'charged_to_user'])
            ->groupBy(fn (SmsOrder $o) => (ProviderModels::forNumberType($o->type) ?? ProviderModels::find('naara_verify'))['key'])
            ->map(fn (Collection $rows) => $rows->sum('charged_to_user'));

        $out = collect();
        foreach ($esimByVoice as $row) {
            $key = $row->has_voice ? 'naara_connect' : 'naara_data';
            $out[$key] = ($out[$key] ?? 0) + (float) $row->total;
        }
        foreach ($numbersByType as $key => $total) {
            $out[$key] = ($out[$key] ?? 0) + (float) $total;
        }

        return $out->map(fn ($total, $key) => [
            'model' => $key,
            'label' => ProviderModels::find($key)['name'] ?? $key,
            'total' => round($total, 2),
        ])->values()->all();
    }

    /**
     * Deposits over time (wallet_transactions type=credit), bucketed by day —
     * a chart of top-up cadence. Never groups by gateway/provider here (that's
     * admin-only territory, §7.2).
     *
     * @return array<int, array{date: string, total: float}>
     */
    public function topUpHistory(User $user, string $range = '90d'): array
    {
        return WalletTransaction::where('user_id', $user->id)
            ->where('type', 'credit')
            ->where('created_at', '>=', $this->since($range))
            ->selectRaw('DATE(created_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'total' => round((float) $row->total, 2)])
            ->all();
    }

    /**
     * How often the user buys — eSIM + number order counts per week.
     *
     * @return array<int, array{week: string, count: int}>
     */
    public function purchaseCadence(User $user, string $range = '90d'): array
    {
        $since = $this->since($range);

        // Bucketed in PHP (not a driver-specific date-format SQL function) so
        // this reads identically on MySQL (production) and SQLite (tests).
        $weekOf = fn (Carbon $d) => $d->format('o-\WW');

        $esims = EsimOrder::where('user_id', $user->id)->where('created_at', '>=', $since)
            ->pluck('created_at')->groupBy($weekOf)->map->count();
        $numbers = SmsOrder::where('user_id', $user->id)->where('created_at', '>=', $since)
            ->pluck('created_at')->groupBy($weekOf)->map->count();

        return $esims->keys()->merge($numbers->keys())->unique()->sort()->values()
            ->map(fn ($week) => ['week' => $week, 'count' => (int) ($esims[$week] ?? 0) + (int) ($numbers[$week] ?? 0)])
            ->all();
    }

    /**
     * "Where you connect" — eSIM purchase count grouped by plan country/region
     * name (never provider). Local plans group by their single country;
     * regional/global plans group by the region label.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function planMixBreakdown(User $user, string $range = '90d'): array
    {
        $orders = EsimOrder::where('user_id', $user->id)
            ->where('created_at', '>=', $this->since($range))
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->with('plan')
            ->get();

        return $orders->filter(fn (EsimOrder $o) => $o->plan !== null)
            ->groupBy(fn (EsimOrder $o) => $this->planMixLabel($o->plan))
            ->map(fn (Collection $rows, string $label) => ['label' => $label, 'count' => $rows->count()])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function planMixLabel(EsimPlan $plan): string
    {
        if ($plan->coverage_type === EsimPlan::COVERAGE_GLOBAL) {
            return 'Global';
        }
        if ($plan->coverage_type === EsimPlan::COVERAGE_REGIONAL) {
            return EsimRegions::label($plan->region_slug);
        }
        $iso = collect((array) $plan->countries)->first();

        return $iso ? CountryNames::name((string) $iso) : 'Unknown';
    }

    private function since(string $range): Carbon
    {
        $days = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: 30;

        return now()->subDays($days);
    }
}
