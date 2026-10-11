<?php

namespace App\Jobs;

use App\Models\PayoutRequest;
use App\Services\Payouts\Guardian\PayoutGuardian;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the Payout Guardian over ONE request on the `payout-guard` queue
 * (Addendum C). Evaluation is read-only and fast; the only mutation is the
 * compare-and-set approval. A Guardian error must FAIL SAFE to human review —
 * never to approval.
 */
class EvaluatePayoutRequestJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 120;

    public function __construct(public int $payoutRequestId)
    {
        $this->onQueue('payout-guard');
    }

    public function uniqueId(): string
    {
        return (string) $this->payoutRequestId;
    }

    public function backoff(): array
    {
        return [30];
    }

    public function handle(PayoutGuardian $guardian): void
    {
        $request = PayoutRequest::find($this->payoutRequestId);
        if ($request !== null) {
            $guardian->evaluate($request);
        }
    }

    /** Exhausted retries: park it with a human, never approve. */
    public function failed(Throwable $e): void
    {
        $request = PayoutRequest::find($this->payoutRequestId);
        if ($request !== null && $request->status === PayoutRequest::PENDING) {
            $request->forceFill([
                'review_state' => PayoutRequest::REVIEW_MANUAL,
                'hold_reason' => 'guardian_error',
                'evaluating_at' => null,
            ])->save();
            AlertAdminJob::dispatch(
                code: 'guardian_error',
                message: "Payout Guardian failed on payout #{$request->id}: ".$e->getMessage(),
                context: ['payout_id' => $request->id],
            );
        }
    }
}
