<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutRequest;
use App\Services\Payouts\Guardian\HoldCheck;
use App\Services\Payouts\Guardian\LedgerHoldVerifier;

/** Helper for the post-paid `returned` state (Addendum D-3.4). */
class ReturnedPayouts
{
    /** A returned payout nets to zero when its original hold has been given back to the bucket. */
    public static function nets(PayoutRequest $request): bool
    {
        $c = app(LedgerHoldVerifier::class)->verify($request);

        return in_array($c->status, [HoldCheck::RELEASED, HoldCheck::UNVERIFIABLE], true);
    }
}
