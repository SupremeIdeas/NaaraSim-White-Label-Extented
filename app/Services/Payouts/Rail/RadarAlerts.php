<?php

namespace App\Services\Payouts\Rail;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Support\Money4;
use Illuminate\Support\Facades\Cache;

/**
 * Funding Radar alerts (Addendum A §6). Each condition alerts at most once per hour per
 * rail (`Cache::add`), with codes namespaced `rail_*`. Read-only on money tables.
 */
class RadarAlerts
{
    public const UNHEALTHY_KEY = 'payout:unhealthy:';

    public function __construct(private FundingRadar $radar) {}

    /** @return list<string> alert codes raised on this run */
    public function run(): array
    {
        $raised = [];

        if ($this->snapshotStale()) {
            $this->raise($raised, 'rail_stale_snapshot', '*', 'The Funding Radar has not produced a snapshot recently — the scheduler or queue may be stuck.');
        }

        foreach (PayoutRailEnrollment::query()->distinct()->pluck('provider') as $provider) {
            $d = $this->radar->cached($provider);
            $max = Money4::units($d['max_exposure']);
            $float = $d['float_available'] === null ? null : Money4::units($d['float_available']);

            if ($d['will_stall']) {
                $this->raise($raised, 'rail_float_short', $provider, "Float at {$provider} ({$d['float_available']} USD) is below what is already committed + due at the next sweep. Payouts will stall — top up.", $d);
            } elseif ($float !== null && $d['recommended_topup_p90'] !== '0.0000') {
                $this->raise($raised, 'rail_float_low_p90', $provider, "Float at {$provider} is below the p90 requirement; recommended top-up {$d['recommended_topup_p90']} USD.", $d);
            }

            if ($max > 0 && ($share = $this->topShare($provider, $max)) > (float) config('payouts.radar.concentration_alert_pct', 20.0)) {
                $this->raise($raised, 'rail_concentration', $provider, sprintf('One user holds %.1f%% of the %s exposure.', $share, $provider));
            }
            if ($this->velocitySpike($provider)) {
                $this->raise($raised, 'rail_velocity_spike', $provider, "Withdrawals on {$provider} in the last hour are far above the trailing average.");
            }
            $this->failureRate($provider, $raised);
        }

        return $raised;
    }

    /** A provider whose recent payouts keep failing is taken out of routing for an hour (re-armed each run while it persists). */
    public static function isUnhealthy(string $provider): bool
    {
        return Cache::has(self::UNHEALTHY_KEY.$provider);
    }

    private function snapshotStale(): bool
    {
        if (! PayoutRailEnrollment::exists()) {
            return false; // nothing to watch yet
        }
        $last = PayoutExposureSnapshot::max('captured_at');

        return $last === null || now()->diffInMinutes($last, false) < -((int) config('payouts.radar.snapshot_stale_minutes', 15));
    }

    private function topShare(string $provider, int $maxUnits): float
    {
        $ids = PayoutRailEnrollment::where('provider', $provider)->whereNotIn('status', [PayoutRailEnrollment::DECLINED])->pluck('user_id');
        $top = collect(app(WithdrawableBalanceResolver::class)->forUsers($ids))->map(fn ($b) => Money4::units($b['total']))->max() ?? 0;

        return $maxUnits > 0 ? $top / $maxUnits * 100 : 0.0;
    }

    private function velocitySpike(string $provider): bool
    {
        $hour = PayoutRequest::where('provider', $provider)->where('created_at', '>=', now()->subHour())->count();
        $trailing = PayoutRequest::where('provider', $provider)->whereBetween('created_at', [now()->subDays(7), now()->subHour()])->count() / (7 * 24);
        $threshold = max(3, (float) config('payouts.radar.velocity_spike_multiple', 3.0) * $trailing);

        return $hour > $threshold;
    }

    private function failureRate(string $provider, array &$raised): void
    {
        $last = PayoutRequest::where('provider', $provider)->whereIn('status', [PayoutRequest::PAID, PayoutRequest::FAILED])
            ->latest('id')->limit(20)->pluck('status');
        if ($last->count() < 10) {
            return; // too few outcomes to judge
        }
        if ($last->filter(fn ($s) => $s === PayoutRequest::FAILED)->count() / $last->count() > 0.10) {
            Cache::put(self::UNHEALTHY_KEY.$provider, true, now()->addHour());
            $this->raise($raised, 'rail_failure_rate', $provider, "More than 10% of the last {$last->count()} payouts on {$provider} failed. The rail is paused for routing until it recovers.");
        }
    }

    private function raise(array &$raised, string $code, string $provider, string $message, array $context = []): void
    {
        if (Cache::add("payout-alert:{$code}:{$provider}", 1, now()->addHour())) {
            AlertAdminJob::dispatch(code: $code, message: $message, context: ['provider' => $provider] + array_intersect_key($context, array_flip(['max_exposure', 'float_available', 'recommended_topup_p90'])));
            $raised[] = $code;
        }
    }
}
