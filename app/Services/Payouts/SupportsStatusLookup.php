<?php

namespace App\Services\Payouts;

use App\Models\PayoutRequest;

/**
 * Optional capability of a payout gateway: ask the PROVIDER what happened to a
 * transfer we submitted, keyed by our own idempotency reference. This is what lets
 * the engine resolve an UNKNOWN outcome (timeout / 5xx / dead worker after submit)
 * without ever guessing — refunding a payout the provider actually made would be a
 * double pay. A gateway with no verified lookup simply does not implement this; the
 * reconciler then sends the request to a human instead of refunding blindly.
 */
interface SupportsStatusLookup
{
    public function lookupTransfer(PayoutRequest $request): LookupResult;
}
