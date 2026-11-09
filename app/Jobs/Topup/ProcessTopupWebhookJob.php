<?php

namespace App\Jobs\Topup;

use App\Models\TopupOrder;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\TopupOrderService;
use App\Services\Topup\TopupProviders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A verified webhook only tells us WHICH order to look at. The supplier is then asked directly (requery) so a forged or replayed payload can
 * never move money; the answer goes through the same OrderService door as polling. A terminal order ignores the event.
 */
class ProcessTopupWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $provider, public string $reference, public string $claimedStatus) {}

    public function handle(TopupOrderService $orders): void
    {
        $o = TopupOrder::where('reference', $this->reference)->where('provider_code', $this->provider)->first();
        if (! $o || $o->isTerminal()) {
            return;
        }
        $r = TopupProviders::get($this->provider)->requery($o->reference, $o->provider_txn_id);
        if ($r->status !== $this->claimedStatus && $r->terminal() === false) {
            $orders->event($o, 'webhook_disagrees', ['claimed' => $this->claimedStatus, 'supplier' => $r->status]);
        }
        $orders->apply($o, $r, 'webhook');
    }
}
