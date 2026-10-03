<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutDecision;
use App\Models\PayoutRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Hourly roll-up for the admin screen and the daily digest. */
class GuardianMetrics
{
    public const CACHE_KEY = 'payouts.guard.metrics';

    /** @return array<string, mixed> */
    public function compute(): array
    {
        $since = now()->subDay();
        $decisions = PayoutDecision::query()->where('decided_at', '>=', $since);

        $byDecision = (clone $decisions)->where('shadow', false)->select('decision', DB::raw('count(*) as n'))->groupBy('decision')->pluck('n', 'decision')->all();
        $shadow = (clone $decisions)->where('shadow', true)->select('decision', DB::raw('count(*) as n'))->groupBy('decision')->pluck('n', 'decision')->all();

        $latency = (clone $decisions)->join('payout_requests', 'payout_requests.id', '=', 'payout_decisions.payout_request_id')
            ->get(['payout_decisions.decided_at', 'payout_requests.created_at'])
            ->map(fn ($r) => abs(\Carbon\Carbon::parse($r->decided_at)->diffInSeconds(\Carbon\Carbon::parse($r->created_at))))->avg();

        $reasons = (clone $decisions)->whereIn('decision', ['hold', 'defer', 'reject'])->select('reason', DB::raw('count(*) as n'))
            ->groupBy('reason')->orderByDesc('n')->limit(5)->pluck('n', 'reason')->all();

        return [
            'as_of' => now()->toIso8601String(),
            'acted_24h' => $byDecision,
            'advisory_24h' => $shadow,
            'avg_decision_latency_s' => $latency === null ? null : round((float) $latency, 1),
            'top_reasons' => $reasons,
            'manual_review' => PayoutRequest::where('status', PayoutRequest::PENDING)->where('review_state', PayoutRequest::REVIEW_MANUAL)->count(),
            'deferred' => PayoutRequest::where('status', PayoutRequest::PENDING)->where('review_state', PayoutRequest::REVIEW_DEFERRED)->count(),
            'qa_sampled_24h' => (clone $decisions)->where('qa_sampled', true)->count(),
            'breaker_active' => Cache::has('guardian-alert:breaker_daily_cap') || Cache::has('guardian-alert:breaker_hourly_rate'),
        ];
    }

    public function roll(): array
    {
        $m = $this->compute();
        Cache::put(self::CACHE_KEY, $m, now()->addHours(3));

        return $m;
    }

    public function latest(): array
    {
        return Cache::get(self::CACHE_KEY) ?? $this->roll();
    }
}
