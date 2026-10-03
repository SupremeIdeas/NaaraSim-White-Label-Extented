<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutAccount;
use App\Models\User;

/**
 * Pluggable sanctions screening hook (Global Payout Layer Phase 5). Deliberately a
 * stub today (NullSanctionsScreening): the country deny list is the live control.
 * Bind a real provider here if volume, a payment provider or counsel ever asks.
 */
interface SanctionsScreening
{
    /** @return bool true when the payee/destination is a HIT and must go to a human */
    public function isHit(User $user, ?PayoutAccount $account): bool;
}
