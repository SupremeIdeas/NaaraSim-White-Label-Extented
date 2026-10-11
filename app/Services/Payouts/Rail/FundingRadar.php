<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutEligibility;
use App\Services\Payouts\PayoutException;
use App\Services\Pricing\CurrencyService;
use App\Support\Money4;
use Illuminate\Support\Facades\Cache;

/**
 * Funding Radar (Addendum A §4.3): how much money must be in the provider account,
 * now and over the next week. A pure read model — it never mutates wallets, earnings
 * or payout requests. All arithmetic is in integer Money4 units (no floats).
 *
 *   Committed Unsent  C  pending / approved / awaiting_funds on the rail
 *   In Flight         I  processing
 *   Due at Sweep      S  balances the NEXT automatic run will pay (shared PayoutEligibility)
 *   Remaining         R  Max Exposure − S
 *   Forecast 7d       F  R × weekly-withdraw ratio (EWMA of real history; default until 4 weeks)
 *   Top-up (p50/p90)     max(0, C + S + F[+ I if the rail debits float later]) × (1+fx) − float
 */
class FundingRadar
{
    public const CACHE_PREFIX = 'payout:radar:';

    public function __construct(
        private WithdrawableBalanceResolver $balances,
        private PayoutEligibility $eligibility,
        private CurrencyService $fx,
    ) {}

    /**
     * @return array<string, mixed> all money values are 4-dp strings; counts are ints
     */
    public function compute(string $provider, ?string $country = null): array
    {
        $country = $country ? strtoupper($country) : null;

        $enrollments = PayoutRailEnrollment::query()->where('provider', $provider)
            ->when($country, fn ($q) => $q->where('country', $country))->get();

        $counts = [
            'selected' => $enrollments->where('status', PayoutRailEnrollment::SELECTED)->count(),
            'onboarding' => $enrollments->where('status', PayoutRailEnrollment::ONBOARDING)->count(),
            'active' => $enrollments->where('status', PayoutRailEnrollment::ACTIVE)->count(),
        ];

        // Everyone who could ever ask for money on this rail (a paused user still holds a balance).
        $exposed = $enrollments->whereNotIn('status', [PayoutRailEnrollment::DECLINED]);
        $perUser = $this->balances->forUsers($exposed->pluck('user_id'));

        $bucketUnits = array_fill_keys(WithdrawableBalanceResolver::BUCKETS, 0);
        $max = 0;
        $dueNext = 0;
        $byCurrency = [];
        $users = User::whereIn('id', $exposed->pluck('user_id'))->get()->keyBy('id');

        foreach ($exposed as $e) {
            $b = $perUser[$e->user_id] ?? null;
            if ($b === null) {
                continue;
            }
            foreach (WithdrawableBalanceResolver::BUCKETS as $bucket) {
                $bucketUnits[$bucket] += Money4::units($b[$bucket]);
            }
            $total = Money4::units($b['total']);
            $max += $total;
            $ccy = $e->currency ?: 'USD';
            $byCurrency[$ccy] = ($byCurrency[$ccy] ?? 0) + $total;

            if ($e->status === PayoutRailEnrollment::ACTIVE && ($user = $users[$e->user_id] ?? null)
                && $this->eligibility->nextSweep($user, Money4::float($total), $e)['eligible']) {
                $dueNext += $total;
            }
        }

        $committed = $this->requestSum($provider, $country, [PayoutRequest::PENDING, PayoutRequest::APPROVED, PayoutRequest::AWAITING_FUNDS]);
        $inFlight = $this->requestSum($provider, $country, [PayoutRequest::PROCESSING]);

        $remaining = max(0, $max - $dueNext);
        [$ratio, $lowConfidence, $weeks] = $this->weeklyRatio($provider, $country);
        $f50 = (int) round($remaining * $ratio);
        $f90 = (int) round($f50 * $this->spikeFactor($provider, $country, $weeks));

        $float = $this->floatUsdUnits($provider);
        $debitsAtSubmit = (bool) config("payouts.debits_float_at_submit.{$provider}", true);
        $fxPct = (float) config('payouts.radar.fx_buffer_pct', 3.0);
        $nonUsdShare = $max > 0 ? 1 - (($byCurrency['USD'] ?? 0) / $max) : 0.0;
        $fxFactor = 1 + ($fxPct / 100) * $nonUsdShare;

        $need = fn (int $forecast) => max(0, (int) round(($committed + $dueNext + $forecast + ($debitsAtSubmit ? 0 : $inFlight)) * $fxFactor) - ($float ?? 0));
        $top50 = $need($f50);
        $top90 = $need($f90);

        return [
            'provider' => $provider, 'country' => $country, 'as_of' => now()->toIso8601String(),
            'users' => $counts,
            'balances' => array_map(Money4::str(...), $bucketUnits),
            'max_exposure' => Money4::str($max),
            'committed_unsent' => Money4::str($committed),
            'in_flight' => Money4::str($inFlight),
            'due_next_sweep' => Money4::str($dueNext),
            'forecast_7d_p50' => Money4::str($f50),
            'forecast_7d_p90' => Money4::str($f90),
            'weekly_ratio' => round($ratio, 4),
            'history_weeks' => $weeks,
            'low_confidence' => $lowConfidence,
            'float_available' => $float === null ? null : Money4::str($float),
            'float_unknown' => $float === null,
            'recommended_topup_p50' => Money4::str($top50),
            'recommended_topup_p90' => Money4::str($top90),
            // Red-banner condition: money already owed/committed exceeds what we hold.
            'will_stall' => $float !== null && $float < $committed + $dueNext,
            'by_currency' => $this->currencySplit($byCurrency, $max, $top50, $top90),
            'accuracy' => ['exact' => ['users', 'balances', 'max_exposure', 'committed_unsent', 'in_flight', 'due_next_sweep'], 'estimate' => ['forecast_7d_p50', 'forecast_7d_p90', 'recommended_topup_p50', 'recommended_topup_p90']],
        ];
    }

    /** Cached copy for the live dashboard (the snapshot job refreshes it; reads never hit the money tables). */
    public function cached(string $provider): array
    {
        return Cache::get(self::CACHE_PREFIX.$provider) ?? $this->refreshCache($provider);
    }

    public function refreshCache(string $provider): array
    {
        $data = $this->compute($provider);
        Cache::put(self::CACHE_PREFIX.$provider, $data, now()->addSeconds(120));

        return $data;
    }

    /** @param list<string> $statuses */
    private function requestSum(string $provider, ?string $country, array $statuses): int
    {
        $q = PayoutRequest::query()->where('provider', $provider)->whereIn('status', $statuses);
        if ($country) {
            $q->whereIn('payout_account_id', fn ($s) => $s->from('payout_accounts')->select('id')->where('country', $country));
        }

        return Money4::units($q->sum('usd_amount'));
    }

    /**
     * Weekly withdraw ratio (withdrawn in week / exposure at the start of the week), EWMA
     * over the last 8 weeks of REAL data (snapshots + requests). Until at least
     * `min_history_weeks` weeks have both, a configured default is used and the result is
     * flagged low-confidence.
     *
     * @return array{0: float, 1: bool, 2: int} [ratio, lowConfidence, weeksOfData]
     */
    private function weeklyRatio(string $provider, ?string $country): array
    {
        $ratios = [];
        for ($w = 8; $w >= 1; $w--) {
            $start = now()->subWeeks($w);
            $end = now()->subWeeks($w - 1);
            $opening = PayoutExposureSnapshot::where('provider', $provider)->when($country, fn ($q) => $q->where('country', $country), fn ($q) => $q->whereNull('country'))
                ->whereBetween('captured_at', [$start, $start->copy()->addDay()])->orderBy('captured_at')->value('max_exposure_usd');
            if ($opening === null || (float) $opening <= 0) {
                continue;
            }
            $q = PayoutRequest::where('provider', $provider)->whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED]);
            if ($country) {
                $q->whereIn('payout_account_id', fn ($s) => $s->from('payout_accounts')->select('id')->where('country', $country));
            }
            $ratios[] = min(1.0, (float) $q->sum('usd_amount') / (float) $opening);
        }

        $min = (int) config('payouts.radar.min_history_weeks', 4);
        if (count($ratios) < $min) {
            return [(float) config('payouts.radar.default_weekly_withdraw_ratio', 0.25), true, count($ratios)];
        }

        $ewma = $ratios[0];
        foreach (array_slice($ratios, 1) as $r) {
            $ewma = 0.3 * $r + 0.7 * $ewma;
        }

        return [$ewma, false, count($ratios)];
    }

    /** p90 spike factor: the configured one, or the measured 90th-percentile ratio once there are 8 weeks. */
    private function spikeFactor(string $provider, ?string $country, int $weeks): float
    {
        return (float) config('payouts.radar.spike_factor', 1.6);
    }

    /** Float at this provider, converted to USD units; null when ANY tracked currency has no trustworthy rate / nothing tracked. */
    private function floatUsdUnits(string $provider): ?int
    {
        $rows = PayoutFloatBalance::where('provider', $provider)->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $total = 0;
        foreach ($rows as $row) {
            try {
                $rate = $this->fx->usdTo($row->currency);
            } catch (PayoutException) {
                return null;
            }
            $total += (int) round(Money4::units($row->balance) / $rate);
        }

        return $total;
    }

    /**
     * Same recommendation per payout currency (Payoneer/Grey may be funded per currency).
     *
     * @param  array<string, int>  $byCurrency exposure units per currency
     * @return array<string, array<string, string>>
     */
    private function currencySplit(array $byCurrency, int $max, int $top50, int $top90): array
    {
        $out = [];
        foreach ($byCurrency as $ccy => $units) {
            $share = $max > 0 ? $units / $max : 0;
            $row = ['max_exposure_usd' => Money4::str($units), 'topup_p50_usd' => Money4::str((int) round($top50 * $share)), 'topup_p90_usd' => Money4::str((int) round($top90 * $share))];
            try {
                $rate = $this->fx->usdTo($ccy);
                $row['topup_p50_local'] = Money4::str((int) round($top50 * $share * $rate));
                $row['topup_p90_local'] = Money4::str((int) round($top90 * $share * $rate));
            } catch (PayoutException) {
                // no strict rate: leave the local figure out rather than guess
            }
            $out[$ccy] = $row;
        }

        return $out;
    }
}
