<?php

namespace App\Jobs\Topup;

use App\Models\TopupOrder;
use App\Services\Topup\TopupOrderService;
use App\Services\Topup\TopupProviders;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Asks the supplier where one pending order stands and applies the answer through the same door as the submit job and webhooks. */
class RequeryOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 55;

    public function __construct(public int $orderId, public string $source = 'requery') {}

    public function uniqueId(): string
    {
        return 'topup-requery-'.$this->orderId;
    }

    public function handle(TopupOrderService $orders): void
    {
        $o = TopupOrder::find($this->orderId);
        if (! $o || ! in_array($o->status, [TopupOrder::PENDING, TopupOrder::SUBMITTING], true)) {
            return;
        }
        $o->update(['last_requery_at' => now(), 'requery_count' => $o->requery_count + 1]);
        $r = TopupProviders::get($o->provider_code)->requery($o->reference, $o->provider_txn_id);
        $orders->apply($o, $r, $this->source);
        if ($o->fresh()->status === TopupOrder::SUBMITTING) {
            $orders->markPending($o->fresh());
        }
    }
}
