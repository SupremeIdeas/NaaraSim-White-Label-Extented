<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutAccount;
use App\Models\User;

class NullSanctionsScreening implements SanctionsScreening
{
    public function isHit(User $user, ?PayoutAccount $account): bool
    {
        return false;
    }
}
