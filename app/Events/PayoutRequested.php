<?php

namespace App\Events;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Events\Dispatchable;

/** A new withdrawal request was created (the Funding Radar listens so its numbers move within seconds). */
class PayoutRequested
{
    use Dispatchable;

    public function __construct(public PayoutRequest $request) {}
}
