<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutDecision;
use App\Support\JobHeartbeats;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;

/**
 * Circuit breakers on the Guardian itself (Addendum C §4). Evaluated BEFORE it
 * approves anything: a runaway (bug, abuse wave, stolen-account spree) is capped
 * by volume, whatever each individual request looks like.
 */
class GuardianBreakers
{
    /** @return array{code: string, evidence: array<string, mixed>}|null */
    public function tripped(float $usd): ?array
    {
        // Fail CLOSED: a stalled sweeper/metrics job means the breakers below are working from stale
        // numbers, so nothing is auto-approved until they run again (Addendum D-3.20).
        if (($stale = $this->staleHeartbeat()) !== null) {
            return $stale;
        }

        $approved = PayoutDecision::query()->where('decision', 'approve')->where('shadow', false);

        $cap = PayoutSettings::dailyAutoCapUsd();
        $day = (float) (clone $approved)->where('payout_decisions.decided_at', '>=', now()->subDay())
            ->join('payout_requests', 'payout_requests.id', '=', 'payout_decisions.payout_request_id')
            ->sum(DB::raw('COALESCE(payout_requests.usd_amount, 0)'));
        if ($cap > 0 && $day + $usd > $cap) {
            return ['code' => 'breaker_daily_cap', 'evidence' => ['approved_24h_usd' => round($day, 2), 'this' => $usd, 'cap' => $cap]];
        }

        $lastHour = (clone $approved)->where('decided_at', '>=', now()->subHour())->count();
        $baseline = (clone $approved)->where('decided_at', '>=', now()->subDays(7))->where('decided_at', '<', now()->subHour())->count() / (7 * 24);
        $limit = max(PayoutSettings::breakerHourlyFloor(), (int) ceil($baseline * 3));
        if ($lastHour + 1 > $limit) {
            return ['code' => 'breaker_hourly_rate', 'evidence' => ['approved_last_hour' => $lastHour, 'limit' => $limit, 'hourly_baseline' => round($baseline, 2)]];
        }

        return null;
    }

    /** @return array{code: string, evidence: array<string, mixed>}|null */
    private function staleHeartbeat(): ?array
    {
        if (! PayoutSettings::failClosedOnStaleHeartbeat()) {
            return null;
        }
        foreach (['payouts:guard-sweep' => 60, 'payouts:guard-metrics' => 3600] as $job => $interval) {
            $last = JobHeartbeats::lastSuccessAt($job);
            if ($last === null || $last->lt(now()->subSeconds($interval * 3))) {
                return ['code' => 'breaker_stale_heartbeat', 'evidence' => ['job' => $job, 'last_success' => $last?->toIso8601String()]];
            }
        }

        return null;
    }
}
