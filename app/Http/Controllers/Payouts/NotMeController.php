<?php

namespace App\Http\Controllers\Payouts;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payouts\Hardening\PayoutFreeze;

/** The "This wasn't me" landing (signed link, Addendum D-3.5). Freezes payouts; no login needed. */
class NotMeController extends Controller
{
    public function __invoke(User $user, PayoutFreeze $freeze)
    {
        $cancelled = $freeze->freeze($user, 'not_me');

        return response()->view('payouts.not-me', ['cancelled' => $cancelled]);
    }
}
