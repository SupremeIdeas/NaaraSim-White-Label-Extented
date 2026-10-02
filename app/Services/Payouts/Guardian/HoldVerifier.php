<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutRequest;

/**
 * Proves the source ledger really holds the money this request is spending
 * (Addendum C, gate G3). Read-only; one implementation reads each bucket's OWN
 * ledger through its existing model — never a second ledger.
 */
interface HoldVerifier
{
    public function verify(PayoutRequest $request): HoldCheck;
}
