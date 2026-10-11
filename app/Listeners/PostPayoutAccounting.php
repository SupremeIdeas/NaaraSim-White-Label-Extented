<?php

namespace App\Listeners;

use App\Events\PayoutRequested;
use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Models\PayoutRequest;
use App\Services\Payouts\Hardening\PayoutAccounting;

/** Posts the balanced accounting lines for each payout event (Addendum D-3.11). Idempotent; never blocks the engine. */
class PostPayoutAccounting
{
    public function __construct(private readonly PayoutAccounting $ledger) {}

    public function handle(PayoutRequested|PayoutSettled|PayoutReversed $event): void
    {
        $r = $event->request;
        try {
            match (true) {
                $event instanceof PayoutRequested => $this->ledger->postHold($r),
                $event instanceof PayoutSettled => $this->ledger->postPaid($r),
                $r->status === PayoutRequest::RETURNED => $this->ledger->postReturned($r),
                default => $this->ledger->postReversal($r),
            };
        } catch (\Throwable $e) {
            // Bookkeeping must never fail a payout; the invariants checker reports a missing posting.
            report($e);
        }
    }
}
