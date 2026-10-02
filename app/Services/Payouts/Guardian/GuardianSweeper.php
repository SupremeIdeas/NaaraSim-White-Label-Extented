<?php

namespace App\Services\Payouts\Guardian;

use App\Jobs\EvaluatePayoutRequestJob;
use App\Models\PayoutRequest;

/**
 * The cron half of the Guardian (Addendum C §6). It only CLAIMS and DISPATCHES —
 * evaluation happens on the `payout-guard` queue, so a slow evaluation can never
 * block a scheduler tick (important on the single-cron shared-host setup).
 *
 * Claiming is one atomic UPDATE per row (`evaluating_at`), so two sweepers — or a
 * sweeper and the creation-time dispatch — never evaluate the same request twice.
 */
class GuardianSweeper
{
    /** A claim older than this is considered abandoned (worker died) and may be re-taken. */
    private const CLAIM_TTL_MINUTES = 5;

    /** A brand-new request gets this long to be picked up by its own creation-time job first. */
    private const NEW_GRACE_MINUTES = 2;

    /** @return int how many requests were claimed and dispatched */
    public function sweep(int $limit = 50, bool $floatOnly = false): int
    {
        $stale = now()->subMinutes(self::CLAIM_TTL_MINUTES);

        $ids = PayoutRequest::query()
            ->where('status', PayoutRequest::PENDING)
            ->where(function ($q) use ($floatOnly) {
                if ($floatOnly) {
                    $q->where('review_state', PayoutRequest::REVIEW_DEFERRED)->where('hold_reason', 'float_short');

                    return;
                }
                $q->where(fn ($d) => $d->where('review_state', PayoutRequest::REVIEW_DEFERRED)->where('next_check_at', '<=', now()))
                    ->orWhere(fn ($n) => $n->where('review_state', PayoutRequest::REVIEW_AUTO_PENDING)
                        ->where('created_at', '<=', now()->subMinutes(self::NEW_GRACE_MINUTES)));
            })
            ->where(fn ($q) => $q->whereNull('evaluating_at')->orWhere('evaluating_at', '<', $stale))
            ->orderBy('id')           // oldest first: FIFO fairness
            ->limit($limit)
            ->pluck('id');

        $claimed = 0;
        foreach ($ids as $id) {
            $won = PayoutRequest::query()->whereKey($id)
                ->where('status', PayoutRequest::PENDING)
                ->where(fn ($q) => $q->whereNull('evaluating_at')->orWhere('evaluating_at', '<', $stale))
                ->update(['evaluating_at' => now()]);

            if ($won === 1) {
                EvaluatePayoutRequestJob::dispatch($id);
                $claimed++;
            }
        }

        return $claimed;
    }
}
