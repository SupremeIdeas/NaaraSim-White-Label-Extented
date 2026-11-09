<?php

namespace App\Jobs\Topup;

use App\Models\TopupOrder;
use App\Services\Topup\TopupOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Scheduled every minute. Backoff: every minute for the first 10 tries, every 5 minutes for the next 10, then every 15. Also flags stale ones. */
class RequeryPendingJob implements ShouldQueue
{
    use Queueable;

    public function handle(TopupOrderService $orders): void
    {
        TopupOrder::whereIn('status', [TopupOrder::PENDING, TopupOrder::SUBMITTING])->orderBy('id')->limit(400)->get()
            ->filter(function (TopupOrder $o) {
                if ($o->status === TopupOrder::SUBMITTING && $o->updated_at->gt(now()->subSeconds(90))) {
                    return false;                                  // the submit job is probably still running
                }
                $gap = $o->requery_count < 10 ? 55 : ($o->requery_count < 20 ? 290 : 890);

                return ! $o->last_requery_at || $o->last_requery_at->lte(now()->subSeconds($gap));
            })
            ->take(200)->each(fn (TopupOrder $o) => RequeryOrderJob::dispatch($o->id));

        $orders->escalateStale();
    }
}
