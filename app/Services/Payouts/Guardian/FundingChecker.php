<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutRequest;

/**
 * Gate G9 asks whether the provider's float covers this payout (plus the already
 * committed, older, unsent ones). The float tables arrive with Phase 4; until then
 * the bound implementation is NullFundingChecker, which says "not tracked".
 */
interface FundingChecker
{
    /** @return array{status: 'ok'|'short'|'not_tracked', evidence: array<string, mixed>} */
    public function check(PayoutRequest $request): array;
}
