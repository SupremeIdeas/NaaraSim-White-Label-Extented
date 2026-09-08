<?php

namespace App\Support;

use App\Models\User;

/**
 * "Has this customer ever paid us?" (Module 25). Voice replies cost money to
 * generate (ElevenLabs usage), so they are reserved for paying customers — a
 * brand-new user gets text chat only at first glance. A user counts as paying
 * once they hold any eSIM/number order or virtual number.
 */
class SpendGate
{
    public static function hasPurchased(User $user): bool
    {
        return $user->esimOrders()->exists()
            || $user->smsOrders()->exists()
            || $user->virtualNumbers()->exists();
    }
}
