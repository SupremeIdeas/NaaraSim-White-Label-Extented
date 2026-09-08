<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * A coupon a user chose to "claim" from an announcement/offer (owner request).
 * When they tap Claim on an offer they land on the catalogue with ?claim=CODE;
 * we stash the code here so their next eSIM/number checkout pre-fills it — a
 * genuine one-tap path from "offer pushed" to "offer applied".
 *
 * This is a convenience pre-fill ONLY. The coupon is still validated and its
 * discount MarginGuard-clamped at checkout, so a stashed code can never force
 * an invalid or unprofitable price.
 */
class PendingCoupon
{
    private const KEY = 'pending_coupon';

    public static function stash(string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code !== '' && strlen($code) <= 40 && ctype_alnum(str_replace(['-', '_'], '', $code))) {
            Session::put(self::KEY, $code);
        }
    }

    public static function peek(): ?string
    {
        return Session::get(self::KEY);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
    }
}
