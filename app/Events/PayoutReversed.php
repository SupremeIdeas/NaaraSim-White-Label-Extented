<?php

namespace App\Events;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a payout could not be sent or was reversed by the PSP. The source
 * layer that HELD the funds (referral-credit bucket, merchant earnings) listens
 * for this to return them — the engine itself is source-agnostic.
 */
class PayoutReversed
{
    use Dispatchable;

    public function __construct(public PayoutRequest $request)
    {
    }
}
