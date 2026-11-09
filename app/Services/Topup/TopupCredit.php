<?php

namespace App\Services\Topup;

use App\Models\TopupProduct;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\CreditSettings;
use App\Support\TopupSettings;

/**
 * Credit discount on DATA top-ups (owner rule: a little credit may be used, it must only trim a small slice of OUR profit, and it can never eat the margin).
 * Computed from the live cost and the live retail price at quote time, so it follows exchange rates and supplier price changes. The discount is the smallest of:
 *   1. our profit share cap: profit x credit_profit_share_pct (default 20%), where profit = retail - cost,
 *   2. what keeps the remaining profit at or above max(topup minimum profit, credit minimum profit),
 *   3. the absolute cap credit_max_discount_usd (default $0.50),
 *   4. the credit settings max redeem % of the retail, and
 *   5. the USD value of the customer's credit balance.
 * It is OFF until the admin switches it on, and airtime is never discounted (data only).
 */
final class TopupCredit
{
    /** @return array{usd: float, credits: float} */
    public static function quote(User $user, TopupProduct $p, float $retail, float $cost): array
    {
        $none = ['usd' => 0.0, 'credits' => 0.0];
        if (! TopupSettings::bool('credit_discount_enabled') || ! CreditSettings::enabled() || $p->type !== 'data') {
            return $none;
        }
        $byBalance = CreditSettings::creditsToUsd(app(CreditService::class)->balance($user));
        $usd = min(self::ceiling($retail, $cost), floor($byBalance * 100) / 100);
        if ($usd < 0.01) {
            return $none;
        }
        $credits = min(app(CreditService::class)->balance($user), CreditSettings::usdToCredits($usd));

        return ['usd' => $usd, 'credits' => round($credits, 2)];
    }

    /** The most a sale of this size could ever be discounted, whoever pays (no balance involved): the offers carousel shows this so a promise is never bigger than what the quote will honour. Rounded DOWN. */
    public static function ceiling(float $retail, float $cost): float
    {
        if (! TopupSettings::bool('credit_discount_enabled') || ! CreditSettings::enabled()) {
            return 0.0;
        }
        $profit = $retail - $cost;
        if ($profit <= 0) {
            return 0.0;
        }
        $floor = max(TopupSettings::float('min_profit_usd'), TopupSettings::float('credit_min_profit_usd'));
        $byShare = $profit * max(0.0, min(100.0, TopupSettings::float('credit_profit_share_pct'))) / 100;
        $byFloor = $profit - $floor;
        $byCap = TopupSettings::float('credit_max_discount_usd');
        $byPct = $retail * (int) CreditSettings::get('max_redeem_pct', 50) / 100;

        return floor(max(0.0, min($byShare, $byFloor, $byCap, $byPct)) * 100) / 100;   // always round DOWN: the platform wins every rounding
    }

    /** Last line of defence at payment time: after the discount we must still hold at least the minimum profit over cost. */
    public static function marginHolds(float $chargedUsd, float $costUsd): bool
    {
        return $chargedUsd - $costUsd >= TopupSettings::float('min_profit_usd') - 0.00001;
    }
}
