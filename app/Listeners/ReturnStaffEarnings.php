<?php

namespace App\Listeners;

use App\Events\PayoutReversed;
use App\Models\User;
use App\Services\Staff\StaffEarningsService;

/**
 * When a staff compensation withdrawal fails or is reversed by the PSP, return
 * the exact earnings that were held (NAARA-BUILD-23). Idempotent on the payout
 * reference, so a repeated reversal never double-credits the staff bucket.
 */
class ReturnStaffEarnings
{
    public function __construct(private StaffEarningsService $earnings) {}

    public function handle(PayoutReversed $event): void
    {
        $request = $event->request;

        if ($request->source_bucket !== 'staff_earnings' || ! $request->credit_amount) {
            return;
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
