<?php

namespace App\Events;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a payout is confirmed paid by the PSP webhook. Source layers can
 * listen to clear their pending hold and notify the payee.
 */
class PayoutSettled
{
    use Dispatchable;

    public function __construct(public PayoutRequest $request)
    {
    }
}
