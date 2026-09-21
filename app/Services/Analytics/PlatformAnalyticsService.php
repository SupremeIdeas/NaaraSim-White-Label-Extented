<?php

namespace App\Services\Analytics;

use App\Models\EsimOrder;
use App\Models\EsimUsageSnapshot;
use App\Models\GiftCardOrder;
use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\MerchantClientSubscription;
use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\PaymentCharge;
use App\Models\PaymentRefund;
use App\Models\ProviderRegistry;
use App\Models\SmsOrder;
use App\Models\SupportConversation;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Services\Pricing\CurrencyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Analytics blueprint §7.2 — admin-scoped sibling to the user-facing
 * ConnectivityAnalyticsService (Part A): one shared, cache-wrapped,
 * chart-ready source of truth for every admin surface that needs a platform
 * number (main dashboard, the dedicated Analytics page, future exports),
 * replacing the ad-hoc inline queries that used to live in Admin\Dashboard.
 *
 * Admin-only figures (revenue, cost, profit, margin): every method here must
 * stay reachable ONLY from Admin\* Livewire components, gated the same way
 * Admin\Dashboard already gates on hasAnyRole(['super_admin','admin']) —
 * never wired into a user-facing component, even indirectly.
 */
class PlatformAnalyticsService
{
    /**
     * Revenue split by product lane (§7.2, fixes gap #1 — Naara Gift used to
     * be invisible here entirely). Every figure is what the user actually
     * paid (price_charged / charged_to_user), never a provider cost.
     *
     * @return array{segments: array<int, array{label: string, value: float, color: string}>, total: float}
     */
    public function revenueBreakdown(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:revenue-breakdown:{$range}", now()->addMinutes(10), function () use ($range) {
            $since = $this->since($range);

            $esimRevenue = $this->esimRevenueSince($since);
            $smsRevenue = $this->smsRevenueSince($since);
            $numberRevenue = $this->numberRevenueSince($since);
            $giftRevenue = $this->giftRevenueSince($since);

            $segments = [
                ['label' => 'eSIM data', 'value' => round($esimRevenue, 2), 'color' => '#0A6E6E'],
                ['label' => 'Virtual numbers', 'value' => round($numberRevenue, 2), 'color' => '#D4A017'],
                ['label' => 'Verification', 'value' => round($smsRevenue - $numberRevenue, 2), 'color' => '#4C9F9F'],
                ['label' => 'Naara Gift', 'value' => round($giftRevenue, 2), 'color' => '#0D1B2A'],
            ];

            return [
                'segments' => $segments,
                'total' => round(array_sum(array_column($segments, 'value')), 2),
            ];
        });
    }

    /**
     * Period-over-period revenue trend (the animated revenue card's delta) —
     * same total as revenueBreakdown(), compared to the prior equal-length
     * window.
     *
     * @return array{current: float, previous: float, delta_pct: ?float}
     */
    public function revenueTrend(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:revenue-trend:{$range}", now()->addMinutes(10), function () use ($range) {
            $days = $this->rangeDays($range);
            $since = now()->subDays($days);
            $prevSince = now()->subDays($days * 2);

            $current = $this->totalRevenueBetween($since, null);
            $previous = $this->totalRevenueBetween($prevSince, $since);

            return [
                'current' => round($current, 2),
                'previous' => round($previous, 2),
                'delta_pct' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
            ];
        });
    }

    /**
     * Daily revenue bars (last N days) — the sum of ALL FOUR lanes per day,
     * since this is pure revenue with no cost involved (unlike profitWindow()
     * below, adding Naara Gift here is unambiguously correct).
     *
     * @return array<int, array{label: string, date: string, value: float}>
     */
    public function dailyRevenueBars(int $days = 7): array
    {
        return Cache::remember("platform-analytics:daily-bars:{$days}", now()->addMinutes(10), function () use ($days) {
            return collect(range($days - 1, 0))->map(function ($back) {
                $day = now()->subDays($back);
                $value = $this->esimRevenueOnDate($day) + $this->smsRevenueOnDate($day) + $this->giftRevenueOnDate($day);

                return ['label' => $day->format('D'), 'date' => $day->toDateString(), 'value' => round($value, 2)];
            })->values()->all();
        });
    }

    /**
     * Profit for a window — revenue MINUS provider cost. Deliberately
     * EXCLUDES Naara Gift: `gift_card_orders` never stores a provider cost
     * per order (money-safety migration comment: "only the retail
     * price_charged is stored, never cost") — GiftCardPricing derives cost
     * live from the product's CURRENT cost_meta, which can drift from the
     * rate actually paid at purchase time. Rather than invent a historical
     * cost by re-deriving it from today's rates, this profit figure stays
     * scoped to the two lanes with a real, persisted, per-order cost
     * (esim_orders.wholesale_cost via OrderLog, sms_orders.provider_cost) —
     * see revenueBreakdown() for Naara Gift's (cost-less) revenue figure.
     */
    public function profitWindow(Carbon $from): float
    {
        return round($this->coreRevenueWindow($from) - $this->costWindow($from), 2);
    }

    /** Revenue for the two lanes with a real, persisted per-order cost (see profitWindow()'s doc block). */
    public function coreRevenueWindow(Carbon $from): float
    {
        return $this->esimRevenueSince($from) + $this->smsRevenueSince($from);
    }

    /** Provider cost for the same two lanes as coreRevenueWindow(). */
    public function costWindow(Carbon $from): float
    {
        return $this->esimCostSince($from) + $this->smsCostSince($from);
    }

    /**
     * Deposit volume per gateway (§7.2, fixes gap #2). Reuses the same
     * gateway-attributed `payment_charges` record FinancialReconciliation
     * already sums by gateway (`in_by_gateway`) — reshaped here as a
     * chart-ready list rather than duplicating a second tracking mechanism.
     * Figures are the CREDITED amount (USD for a converted top-up, else the
     * gateway's own currency) — see topUpVolumeByCurrency() for the ORIGINAL
     * paid-currency mix instead.
     *
     * @return array<int, array{gateway: string, volume: float}>
     */
    public function topUpVolumeByGateway(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:topup-by-gateway:{$range}", now()->addMinutes(10), function () use ($range) {
            return PaymentCharge::where('created_at', '>=', $this->since($range))
                ->selectRaw('gateway, SUM(amount) as vol')
                ->groupBy('gateway')
                ->get()
                ->map(fn ($row) => ['gateway' => (string) $row->gateway, 'volume' => round((float) $row->vol, 2)])
                ->sortByDesc('volume')->values()->all();
        });
    }

    /**
     * Deposit volume by the currency the user actually paid in (§7.2, fixes
     * gap #2) — the FX-mix view `payment_charges` can't answer, since it
     * stores the CREDITED currency, not the original one. Reads
     * wallet_transactions.paid_currency/paid_amount (Part B §3.2), falling
     * back to currency/amount for a same-currency credit (paid_currency null
     * — nothing was converted).
     *
     * @return array<int, array{currency: string, volume: float}>
     */
    public function topUpVolumeByCurrency(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:topup-by-currency:{$range}", now()->addMinutes(10), function () use ($range) {
            return WalletTransaction::where('type', 'credit')
                ->where('created_at', '>=', $this->since($range))
                ->get(['paid_currency', 'currency', 'paid_amount', 'amount'])
                ->groupBy(fn (WalletTransaction $t) => $t->paid_currency ?: $t->currency)
                ->map(fn ($rows, $ccy) => [
                    'currency' => (string) $ccy,
                    'volume' => round((float) $rows->sum(fn (WalletTransaction $t) => (float) ($t->paid_amount ?? $t->amount)), 2),
                ])
                ->sortByDesc('volume')->values()->all();
        });
    }

    /**
     * What the platform currently "owes" users in aggregate — every wallet's
     * spendable usd_balance, summed (§7.2, fixes gap #2). Point-in-time, not
     * range-scoped. Same figure FinancialReconciliation::report() calls
     * outstanding_usd; exposed here too since Analytics is meant to be the
     * one-stop platform-overview surface (§7.3).
     */
    public function platformUsdLiability(): float
    {
        return Cache::remember('platform-analytics:usd-liability', now()->addMinutes(10),
            fn () => round((float) UserWallet::sum('usd_balance'), 2));
    }

    /**
     * The live USD→currency rates every conversion in the app is CURRENTLY
     * using (§7.2, fixes gap #2) — reuses CurrencyService::rate() per
     * supported currency (never re-fetches FX independently), so this can
     * never disagree with what a real top-up/price conversion just used.
     *
     * @return array<int, array{currency: string, usd_rate: float}>
     */
    public function fxRateSnapshot(): array
    {
        return Cache::remember('platform-analytics:fx-snapshot', now()->addMinutes(15), function () {
            $fx = app(CurrencyService::class);

            return collect(CurrencyService::SUPPORTED)->keys()
                ->map(fn ($code) => ['currency' => $code, 'usd_rate' => $fx->rate($code)])
                ->values()->all();
        });
    }

    /**
     * Top merchants by client-order volume (§7.2, fixes gap #3 — nothing
     * rolled merchant activity up into the platform overview before). Volume
     * is the retail price_charged of orders behind an active client
     * subscription, joined through merchant_client_subscriptions — the same
     * link Merchant V2's own client-management pages use, never a duplicate
     * revenue tracker.
     *
     * @return array<int, array{merchant_id: int, business_name: string, volume: float, clients: int}>
     */
    public function merchantVolumeLeaderboard(string $range = '30d', int $limit = 10): array
    {
        return Cache::remember("platform-analytics:merchant-leaderboard:{$range}:{$limit}", now()->addMinutes(10), function () use ($range, $limit) {
            $rows = MerchantClientSubscription::query()
                ->join('esim_orders', 'esim_orders.id', '=', 'merchant_client_subscriptions.esim_order_id')
                ->where('esim_orders.created_at', '>=', $this->since($range))
                ->selectRaw('merchant_client_subscriptions.merchant_id as merchant_id, '.
                    'SUM(esim_orders.price_charged) as volume, '.
                    'COUNT(DISTINCT merchant_client_subscriptions.merchant_client_id) as clients')
                ->groupBy('merchant_client_subscriptions.merchant_id')
                ->orderByDesc('volume')
                ->limit($limit)
                ->get();

            $names = Merchant::whereIn('id', $rows->pluck('merchant_id'))->pluck('business_name', 'id');

            return $rows->map(fn ($row) => [
                'merchant_id' => (int) $row->merchant_id,
                'business_name' => $names[$row->merchant_id] ?? 'Unknown',
                'volume' => round((float) $row->volume, 2),
                'clients' => (int) $row->clients,
            ])->values()->all();
        });
    }

    /** Total merchant commission accrued in the window (their earnings, not platform revenue). */
    public function merchantEarningsTotal(string $range = '30d'): float
    {
        return Cache::remember("platform-analytics:merchant-earnings-total:{$range}", now()->addMinutes(10),
            fn () => round((float) MerchantEarning::where('type', MerchantEarning::ACCRUAL)
                ->where('created_at', '>=', $this->since($range))->sum('amount'), 2));
    }

    /**
     * KYC approval rate over the window (§7.2, fixes gap #4) — only counts
     * FINAL decisions (approved/rejected/failed); a still-pending verification
     * isn't a decision yet, so it's excluded rather than counted as a "no".
     *
     * @return array{approved: int, total: int, rate_pct: ?float}
     */
    public function kycApprovalRate(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:kyc-rate:{$range}", now()->addMinutes(10), function () use ($range) {
            $final = KycVerification::where('created_at', '>=', $this->since($range))
                ->whereIn('status', [KycVerification::APPROVED, KycVerification::REJECTED, KycVerification::FAILED])
                ->get(['status']);
            $approved = $final->where('status', KycVerification::APPROVED)->count();
            $total = $final->count();

            return [
                'approved' => $approved,
                'total' => $total,
                'rate_pct' => $total > 0 ? round($approved / $total * 100, 1) : null,
            ];
        });
    }

    /**
     * Tier 5 #15 Phase B — what fraction of KYC submissions the automated
     * providers (anything except 'manual') resolved outright versus how
     * many needed a human decision. Only counts FINAL decisions, same
     * philosophy as kycApprovalRate() — a still-pending verification isn't
     * a resolution yet.
     *
     * @return array{automated: int, escalated: int, total: int, automated_pct: ?float}
     */
    public function kycAutomationResolutionRate(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:kyc-automation-rate:{$range}", now()->addMinutes(10), function () use ($range) {
            $final = KycVerification::where('created_at', '>=', $this->since($range))
                ->whereIn('status', [KycVerification::APPROVED, KycVerification::REJECTED, KycVerification::FAILED])
                ->get(['provider']);

            $automated = $final->where('provider', '!=', 'manual')->count();
            $total = $final->count();

            return [
                'automated' => $automated,
                'escalated' => $total - $automated,
                'total' => $total,
                'automated_pct' => $total > 0 ? round($automated / $total * 100, 1) : null,
            ];
        });
    }

    /**
     * Refunds as a % of total revenue over the window (§7.2, fixes gap #4).
     * Only settled refunds (STATUS_DONE) count — a pending/failed refund
     * request hasn't actually moved money yet.
     *
     * @return array{refunds: float, revenue: float, rate_pct: ?float}
     */
    public function refundRateVsRevenue(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:refund-rate:{$range}", now()->addMinutes(10), function () use ($range) {
            $since = $this->since($range);
            $refunds = (float) PaymentRefund::where('created_at', '>=', $since)
                ->where('status', PaymentRefund::STATUS_DONE)->sum('amount');
            $revenue = $this->totalRevenueBetween($since, null);

            return [
                'refunds' => round($refunds, 2),
                'revenue' => round($revenue, 2),
                'rate_pct' => $revenue > 0 ? round($refunds / $revenue * 100, 1) : null,
            ];
        });
    }

    /**
     * Support queue trend (§7.2, fixes gap #4) — open vs resolved counts, plus
     * an average resolution time. There is no dedicated resolved_at column on
     * support_conversations, but the resolve action (Admin\SupportQueue)
     * always saves the model when it sets status to resolved/closed, so
     * updated_at - created_at on a resolved ticket is a real (if coarse)
     * signal, never an invented one.
     *
     * @return array{open: int, resolved: int, avg_resolution_hours: ?float}
     */
    public function supportQueueTrend(string $range = '30d'): array
    {
        return Cache::remember("platform-analytics:support-trend:{$range}", now()->addMinutes(10), function () use ($range) {
            $since = $this->since($range);
            $open = SupportConversation::where('created_at', '>=', $since)
                ->whereNotIn('status', ['resolved', 'closed'])->count();
            $resolved = SupportConversation::where('created_at', '>=', $since)
                ->whereIn('status', ['resolved', 'closed'])->get(['created_at', 'updated_at']);

            $avgHours = $resolved->isNotEmpty()
                ? round($resolved->avg(fn ($c) => $c->created_at->diffInMinutes($c->updated_at)) / 60, 1)
                : null;

            return [
                'open' => $open,
                'resolved' => $resolved->count(),
                'avg_resolution_hours' => $avgHours,
            ];
        });
    }

    /**
     * Provider reliability headline (§7.2, fixes gap #5) — a THIN read of the
     * NCI reliability system that already exists (ProviderRegistry.
     * success_rate_24h / circuit_breaker_state, written by
     * CircuitBreaker::record(), BUILD-15/17). Deliberately NOT a new tracking
     * mechanism — the platform already has a full Operations Center
     * (Admin\Nci\HealthMonitor) for per-provider drill-down; this only
     * surfaces the headline for the platform overview, linking out to that
     * page for depth (see the Admin\Analytics view).
     *
     * @return array{tracked: int, degraded: int, avg_success_rate_pct: ?float, worst: array<int, array{provider_key: string, success_rate_pct: ?float, circuit_breaker_state: string}>}
     */
    public function providerReliabilitySummary(): array
    {
        return Cache::remember('platform-analytics:provider-reliability', now()->addMinutes(5), function () {
            $rows = ProviderRegistry::where('enabled', true)->get(['provider_key', 'success_rate_24h', 'circuit_breaker_state']);
            $tracked = $rows->whereNotNull('success_rate_24h');

            $worst = $tracked->sortBy('success_rate_24h')->take(5)
                ->map(fn ($r) => [
                    'provider_key' => $r->provider_key,
                    'success_rate_pct' => round((float) $r->success_rate_24h * 100, 1),
                    'circuit_breaker_state' => $r->circuit_breaker_state,
                ])->values()->all();

            return [
                'tracked' => $tracked->count(),
                'degraded' => $rows->where('circuit_breaker_state', '!=', 'closed')->count(),
                'avg_success_rate_pct' => $tracked->isNotEmpty() ? round((float) $tracked->avg('success_rate_24h') * 100, 1) : null,
                'worst' => $worst,
            ];
        });
    }

    /**
     * Platform-wide eSIM usage this week (§7.2, ties back to Part A) — the
     * SAME esim_usage_snapshots table Part A's per-user analytics reads,
     * just queried without the per-user scope. Data used is a real
     * snapshot-to-snapshot delta per order (data_used_mb is cumulative since
     * activation, never a per-day figure), mirroring the customer-facing
     * weeklyDataUsage() philosophy — never invented, zero when insufficient
     * history exists for an order.
     *
     * @return array{total_mb_this_week: float, active_esims: int, expiring_soon: int}
     */
    public function platformEsimUsageSummary(): array
    {
        return Cache::remember('platform-analytics:esim-usage-summary', now()->addMinutes(15), function () {
            $weekAgo = now()->subDays(7);

            $totalMb = 0.0;
            EsimUsageSnapshot::where('captured_at', '>=', $weekAgo->copy()->subDay())
                ->orderBy('captured_at')
                ->get(['esim_order_id', 'data_used_mb', 'captured_at'])
                ->groupBy('esim_order_id')
                ->each(function ($snapshots) use (&$totalMb, $weekAgo) {
                    $previous = null;
                    foreach ($snapshots as $snapshot) {
                        if ($previous !== null) {
                            $delta = (int) $snapshot->data_used_mb - (int) $previous->data_used_mb;
                            if ($delta > 0 && $snapshot->captured_at->gte($weekAgo)) {
                                $totalMb += $delta;
                            }
                        }
                        $previous = $snapshot;
                    }
                });

            return [
                'total_mb_this_week' => round($totalMb, 1),
                'active_esims' => EsimOrder::where('status', 'active')->count(),
                'expiring_soon' => EsimOrder::where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(3)])
                    ->count(),
            ];
        });
    }

    /**
     * Tier 5 #15 Phase A — real order volume attributable to one internal
     * provider over the window, for the admin overview's per-provider
     * cards. `$stack` picks the right ledger (esim_orders vs sms_orders —
     * the latter covers OTP/rental/permanent, all attributed via the same
     * `provider` column).
     */
    public function providerOrderVolume(string $stack, string $providerKey, string $range = '30d'): int
    {
        return Cache::remember("platform-analytics:provider-volume:{$stack}:{$providerKey}:{$range}", now()->addMinutes(10), function () use ($stack, $providerKey, $range) {
            $since = $this->since($range);

            return $stack === 'esim'
                ? EsimOrder::where('provider', $providerKey)->where('created_at', '>=', $since)->count()
                : SmsOrder::where('provider', $providerKey)->where('created_at', '>=', $since)
                    ->whereNotIn('status', ['timeout', 'cancelled'])->count();
        });
    }

    private function esimRevenueSince(Carbon $since): float
    {
        return (float) EsimOrder::where('created_at', '>=', $since)->sum('price_charged');
    }

    private function esimRevenueOnDate(Carbon $day): float
    {
        return (float) EsimOrder::whereDate('created_at', $day->toDateString())->sum('price_charged');
    }

    private function esimCostSince(Carbon $since): float
    {
        return (float) OrderLog::where('created_at', '>=', $since)->sum('provider_cost');
    }

    private function smsRevenueSince(Carbon $since): float
    {
        return (float) SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user');
    }

    private function smsRevenueOnDate(Carbon $day): float
    {
        return (float) SmsOrder::whereDate('created_at', $day->toDateString())
            ->whereNotIn('status', ['timeout', 'cancelled'])->sum('charged_to_user');
    }

    private function smsCostSince(Carbon $since): float
    {
        return (float) SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])->sum('provider_cost');
    }

    /** Virtual-number (permanent) revenue — the twilio/telnyx lane of sms_orders. */
    private function numberRevenueSince(Carbon $since): float
    {
        return (float) SmsOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', ['timeout', 'cancelled'])
            ->whereIn('provider', ['twilio', 'telnyx'])->sum('charged_to_user');
    }

    private function giftRevenueSince(Carbon $since): float
    {
        return (float) GiftCardOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', [GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED])
            ->sum('price_charged');
    }

    private function giftRevenueOnDate(Carbon $day): float
    {
        return (float) GiftCardOrder::whereDate('created_at', $day->toDateString())
            ->whereNotIn('status', [GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED])
            ->sum('price_charged');
    }

    /** Total revenue (all 4 lanes) in [$since, $until) — $until null means "through now". */
    private function totalRevenueBetween(Carbon $since, ?Carbon $until): float
    {
        $esim = EsimOrder::where('created_at', '>=', $since);
        $sms = SmsOrder::where('created_at', '>=', $since)->whereNotIn('status', ['timeout', 'cancelled']);
        $gift = GiftCardOrder::where('created_at', '>=', $since)
            ->whereNotIn('status', [GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED]);

        if ($until !== null) {
            $esim->where('created_at', '<', $until);
            $sms->where('created_at', '<', $until);
            $gift->where('created_at', '<', $until);
        }

        return (float) $esim->sum('price_charged') + (float) $sms->sum('charged_to_user') + (float) $gift->sum('price_charged');
    }

    /** Parses a range string like '30d'/'7d'/'90d' into a day count (default 30). */
    private function rangeDays(string $range): int
    {
        return (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT) ?: 30;
    }

    private function since(string $range): Carbon
    {
        return now()->subDays($this->rangeDays($range));
    }
}
