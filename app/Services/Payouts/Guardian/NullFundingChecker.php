<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutRequest;

/** Float is not tracked yet (Phase 4): local rails draw on the provider's own balance. */
class NullFundingChecker implements FundingChecker
{
    public function check(PayoutRequest $request): array
    {
        return ['status' => 'not_tracked', 'evidence' => ['note' => 'float tracking not enabled']];
    }
}
