<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Models\User;
use App\Services\Referrals\ReferralEarningsService;

/**
 * When a referral-earnings withdrawal fails or is reversed by the PSP, return the
 * exact earnings that were held (NAARA-BUILD-22). Idempotent on the payout
 * reference, so a repeated reversal never double-credits the referral bucket.
 */
class ReturnReferralEarnings
{
    public function __construct(private ReferralEarningsService $earnings) {}

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'referral_earnings' || ! $request->credit_amount) {
            return; // not a referral-earnings withdrawal
        }

        $user = $request->user ?? User::find($request->user_id);
        if ($user === null) {
            return;
        }

        $this->earnings->release(
            $user,
            (float) $request->credit_amount,
            'earn-release:'.$request->reference,
            'Withdrawal returned',
        );
    }
}
