<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutAccount;
use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\PayoutWithdrawalStatHourly;
use App\Support\Money4;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduled half of the Funding Radar: writes the append-only snapshot series,
 * refreshes the live cache keys, maintains the hourly withdrawal stats and prunes
 * history (raw 5-min rows 14 days → hourly to 90 days → daily forever).
 */
class RadarSnapshotter
{
    public function __construct(private FundingRadar $radar) {}

    /** @return list<string> every rail that has enrollments, plus the configured global providers */
    public function providers(): array
    {
        return array_values(array_unique(array_merge(
            RailEnrollmentService::globalProviders(),
            PayoutRailEnrollment::query()->distinct()->pluck('provider')->all(),
        )));
    }

    /** One snapshot row per provider (all countries) and per provider+country. @return int rows written */
    public function snapshot(?Carbon $at = null): int
    {
        $at ??= now();
        $rows = 0;

        foreach ($this->providers() as $provider) {
            $this->write($at, $this->radar->refreshCache($provider));
            $rows++;

            $countries = PayoutRailEnrollment::where('provider', $provider)->distinct()->pluck('country');
            foreach ($countries as $country) {
                $this->write($at, $this->radar->compute($provider, $country));
                $rows++;
            }
        }
        Cache::put('payout:radar:last_snapshot', $at->toIso8601String(), now()->addDay());

        return $rows;
    }

    private function write(Carbon $at, array $d): void
    {
        $u = fn (string $k) => Money4::str(Money4::units($d[$k] ?? 0));

        PayoutExposureSnapshot::create([
            'captured_at' => $at, 'provider' => $d['provider'], 'country' => $d['country'],
            'users_selected' => $d['users']['selected'], 'users_onboarding' => $d['users']['onboarding'], 'users_active' => $d['users']['active'],
            'balance_credits_usd' => $d['balances']['credits'], 'balance_referral_usd' => $d['balances']['referral'],
            'balance_merchant_usd' => $d['balances']['merchant'], 'balance_partner_usd' => $d['balances']['partner'], 'balance_staff_usd' => $d['balances']['staff'],
            'max_exposure_usd' => $d['max_exposure'], 'committed_unsent_usd' => $d['committed_unsent'], 'in_flight_usd' => $d['in_flight'],
            'due_next_sweep_usd' => $d['due_next_sweep'], 'forecast_7d_p50_usd' => $d['forecast_7d_p50'], 'forecast_7d_p90_usd' => $d['forecast_7d_p90'],
            'float_available_usd' => $d['float_available'], 'recommended_topup_p50_usd' => $d['recommended_topup_p50'],
            'recommended_topup_p90_usd' => $d['recommended_topup_p90'], 'low_confidence' => $d['low_confidence'],
        ]);
    }

    /**
     * Idempotently recompute the hourly buckets for the last `$hours` hours (the window
     * is wider than one hour so a late webhook corrects the bucket it belongs to).
     *
     * @return int buckets written
     */
    public function statsHourly(int $hours = 2): int
    {
        $from = now()->startOfHour()->subHours($hours - 1);
        $requests = PayoutRequest::query()->where('created_at', '>=', $from)->whereNotNull('provider')->get();
        $countries = PayoutAccount::whereIn('id', $requests->pluck('payout_account_id'))->pluck('country', 'id');

        $buckets = [];
        foreach ($requests as $r) {
            $key = $r->created_at->copy()->startOfHour()->toDateTimeString().'|'.$r->provider.'|'.strtoupper($countries[$r->payout_account_id] ?? 'ZZ');
            $b = &$buckets[$key];
            $b ??= ['requests' => 0, 'req' => 0, 'paid' => 0, 'paid_usd' => 0, 'failed' => 0, 'failed_usd' => 0, 'ttp' => []];
            $usd = Money4::units($r->usd_amount);
            $b['requests']++;
            $b['req'] += $usd;
            if ($r->status === PayoutRequest::PAID) {
                $b['paid']++;
                $b['paid_usd'] += $usd;
                $r->settled_at && $b['ttp'][] = (int) abs($r->settled_at->diffInSeconds($r->created_at));
            } elseif (in_array($r->status, [PayoutRequest::FAILED, PayoutRequest::REVERSED], true)) {
                $b['failed']++;
                $b['failed_usd'] += $usd;
            }
            unset($b);
        }

        // Make sure buckets that emptied (all requests re-bucketed) are reset, not left stale.
        PayoutWithdrawalStatHourly::where('hour_start', '>=', $from)->get()->each(function ($row) use ($buckets) {
            $key = $row->hour_start->toDateTimeString().'|'.$row->provider.'|'.$row->country;
            isset($buckets[$key]) || $row->delete();
        });

        foreach ($buckets as $key => $b) {
            [$hour, $provider, $country] = explode('|', $key);
            PayoutWithdrawalStatHourly::updateOrCreate(
                ['hour_start' => $hour, 'provider' => $provider, 'country' => $country],
                [
                    'requests_count' => $b['requests'], 'requested_usd' => Money4::str($b['req']),
                    'paid_count' => $b['paid'], 'paid_usd' => Money4::str($b['paid_usd']),
                    'failed_count' => $b['failed'], 'failed_usd' => Money4::str($b['failed_usd']),
                    'avg_time_to_paid_sec' => $b['ttp'] === [] ? null : (int) round(array_sum($b['ttp']) / count($b['ttp'])),
                ],
            );
        }

        return count($buckets);
    }

    /** Raw 5-minute rows past 14 days keep one per hour; past 90 days one per day. @return int rows removed */
    public function prune(): int
    {
        $removed = 0;
        $rows = PayoutExposureSnapshot::where('captured_at', '<', now()->subDays(14))
            ->orderBy('id')->get(['id', 'captured_at', 'provider', 'country']);

        $keep = [];
        foreach ($rows as $r) {
            $old = $r->captured_at->lt(now()->subDays(90));
            $bucket = $r->provider.'|'.($r->country ?? '*').'|'.$r->captured_at->format($old ? 'Y-m-d' : 'Y-m-d H');
            $keep[$bucket] = $r->id; // newest id in the bucket wins
        }
        $drop = $rows->pluck('id')->diff(array_values($keep));
        foreach ($drop->chunk(500) as $chunk) {
            $removed += PayoutExposureSnapshot::whereIn('id', $chunk)->delete();
        }

        return $removed;
    }
}
