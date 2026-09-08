<?php

namespace App\Jobs;

use App\Models\PayoutRequest;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The external PSP transfer runs in a queued job (money-safety rule 8 — no
 * external call in the request cycle). ShouldBeUnique keeps a duplicate send off
 * the queue; PayoutService::send() is itself row-locked and refuses any
 * non-actionable state, so this can never double-send.
 */
class SendPayoutJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1; // money action — never blind-retry

    public function __construct(public int $payoutRequestId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->payoutRequestId;
    }

    public function handle(PayoutService $payouts): void
    {
        $request = PayoutRequest::find($this->payoutRequestId);
        if ($request !== null) {
            $payouts->send($request);
        }
    }
}
