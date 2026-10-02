<?php

namespace App\Jobs;

use App\Models\PayoutProviderCall;
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

    /** Provider HTTP total timeout is 25s; the worker gets a little more, never less (Addendum D-3.2). */
    public int $timeout = 40;

    /** A killed worker must not leave the unique lock behind and silently swallow later dispatches. */
    public int $uniqueFor = 900;

    public function __construct(public int $payoutRequestId)
    {
        $this->onQueue('payouts');
    }

    public function uniqueId(): string
    {
        return (string) $this->payoutRequestId;
    }

    /**
     * The worker died / timed out / was killed mid-call. If it had already reached the
     * provider we cannot know what happened — mark the open call `unknown` so the
     * reconciler resolves it from the provider. NEVER leave `submitted` dangling and
     * NEVER reverse here.
     */
    public function failed(\Throwable $e): void
    {
        PayoutProviderCall::query()
            ->where('payout_request_id', $this->payoutRequestId)
            ->whereIn('state', [PayoutProviderCall::INTENT, PayoutProviderCall::SUBMITTED])
            ->update([
                'state' => PayoutProviderCall::UNKNOWN,
                'error' => mb_substr('Worker failed: '.$e->getMessage(), 0, 1000),
                'finished_at' => now(),
                'next_lookup_at' => now()->addMinutes(2),
            ]);
    }

    public function handle(PayoutService $payouts): void
    {
        $request = PayoutRequest::find($this->payoutRequestId);
        if ($request !== null) {
            $payouts->send($request);
        }
    }
}
