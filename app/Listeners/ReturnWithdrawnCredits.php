<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Models\PayoutRequest;
use App\Services\Credits\CreditService;

/**
 * When a credit-sourced withdrawal fails or is reversed by the PSP, return the
 * exact credits that were held (ROADMAP §Layer 1). Idempotent on the payout
 * reference, so a repeated reversal never double-credits.
 */
class ReturnWithdrawnCredits
{
    public function __construct(private CreditService $credits)
    {
    }

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'referral_credits' || ! $request->credit_amount) {
            return; // not a credit withdrawal
        }

        $this->credits->earn(
            $request->user,
            (float) $request->credit_amount,
            'withdraw_refund',
            'wd-refund:'.$request->reference,
            'Withdrawal returned',
            withdrawable: true,
        );
    }
}
