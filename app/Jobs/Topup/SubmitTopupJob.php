<?php

namespace App\Jobs\Topup;

use App\Models\TopupOrder;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\TopupOrderService;
use App\Services\Topup\TopupProviders;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Sends ONE order to its supplier. Queued (money-safety rule 8), unique per order, and never double-sends: a retry asks the supplier first. */
class SubmitTopupJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public int $orderId, public bool $fresh = false) {}

    public function uniqueId(): string
    {
        return 'topup-submit-'.$this->orderId.($this->fresh ? '-f' : '');
    }

    public function backoff(): array
    {
        return [20, 60];
    }

    public function handle(TopupOrderService $orders): void
    {
        $order = DB::transaction(function () use ($orders) {
            $o = TopupOrder::lockForUpdate()->find($this->orderId);
            if (! $o || ! in_array($o->status, [TopupOrder::FUNDS_HELD, TopupOrder::SUBMITTING], true)) {
                return null;
            }
            if ($o->status === TopupOrder::FUNDS_HELD) {
                $orders->transition($o, TopupOrder::SUBMITTING);
            }
            $o->increment('attempts');

            return $o->fresh();
        });
        if (! $order) {
            return;
        }

        $provider = TopupProviders::get($order->provider_code);

        // A retry (worker crashed / timed out mid-call) asks the supplier BEFORE sending anything.
        if ($this->attempts() > 1 && ! $this->fresh) {
            $q = $provider->requery($order->reference, $order->provider_txn_id);
            if ($q->status !== PurchaseResult::PENDING || $q->providerTxnId) {
                $orders->apply($order, $q, 'resume');

                return;
            }
        }

        $order->update(['submitted_at' => $order->submitted_at ?? now()]);
        $result = $provider->purchase($orders->request($order));
        $orders->apply($order, $result, 'submit');
    }

    public function failed(Throwable $e): void
    {
        // Unknown outcome: leave it for the requery loop. Never refund on an exception.
        $o = TopupOrder::find($this->orderId);
        if ($o && $o->status === TopupOrder::SUBMITTING) {
            app(TopupOrderService::class)->event($o, 'job_failed', ['error' => substr($e->getMessage(), 0, 200)]);
            app(TopupOrderService::class)->markPending($o);
        }
    }
}
