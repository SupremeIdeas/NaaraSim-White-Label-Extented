<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Currency-aware rounding for payout amounts (Addendum D-3.13). The LOCAL amount is rounded once,
 * to the currency's own minor-unit count, half-even (banker's rounding, no systematic bias), at the
 * moment it is locked on the request — and that stored figure is what is sent and displayed, never a
 * re-derived one. Zero-decimal (JPY, UGX, RWF …) and three-decimal (KWD, BHD …) currencies are listed
 * in config/payouts.php and can be overridden by the admin (payouts.currency_decimals JSON).
 */
class PayoutMoney
{
    public static function decimals(string $currency): int
    {
        $currency = strtoupper($currency);
        $override = json_decode((string) Setting::getValue('payouts.currency_decimals', '{}'), true);
        if (is_array($override) && isset($override[$currency])) {
            return max(0, min(4, (int) $override[$currency]));
        }
        $table = (array) config('payouts.currency_decimals', []);

        return (int) ($table[$currency] ?? $table['default'] ?? 2);
    }

    public static function round(float $amount, string $currency): float
    {
        return round($amount, self::decimals($currency), PHP_ROUND_HALF_EVEN);
    }

    /** The smallest amount the currency can express (e.g. 0.01, 1, 0.001). */
    public static function minorUnit(string $currency): float
    {
        return 1 / (10 ** self::decimals($currency));
    }
}
