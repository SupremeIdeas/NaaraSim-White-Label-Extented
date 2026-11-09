<?php

namespace App\Support;

use App\Models\TopupOrder;
use App\Models\User;
use App\Services\Topup\TopupException;

/**
 * Airtime is instant and unrecoverable, so a chargeback or a drained wallet lands on us directly. Every limit is admin-set
 * (Admin > Top-ups > Limits): per-order min/max, per-user daily/weekly spend, lower caps for brand-new accounts, distinct-recipient and
 * hourly-order velocity.
 */
class TopupFraud
{
    /** Orders that count toward velocity: anything not failed/refunded/payment_failed. */
    private static function counted(User $u, \DateTimeInterface $since)
    {
        return TopupOrder::where('user_id', $u->id)->whereNotIn('status', [TopupOrder::FAILED, TopupOrder::REFUNDED, TopupOrder::PAYMENT_FAILED])->where('created_at', '>=', $since);
    }

    public static function assert(User $user, float $priceUsd, string $phoneE164): void
    {
        if ($priceUsd < TopupSettings::float('min_order_usd')) {
            throw new TopupException('The minimum top-up is $'.number_format(TopupSettings::float('min_order_usd'), 2).'.');
        }
        if ($priceUsd > TopupSettings::float('max_order_usd')) {
            throw new TopupException('The maximum for a single top-up is $'.number_format(TopupSettings::float('max_order_usd'), 2).'.');
        }

        $day = self::counted($user, now()->subDay());
        $daySum = (float) (clone $day)->sum('price_usd');
        $isNew = $user->created_at && $user->created_at->gt(now()->subHours(TopupSettings::int('new_account_hours')));
        $cap = $isNew ? TopupSettings::float('new_account_daily_cap_usd') : TopupSettings::float('daily_cap_usd');
        if ($daySum + $priceUsd > $cap) {
            throw new TopupException($isNew ? 'For your security, new accounts have a lower daily top-up limit. Try a smaller amount, or come back a little later.' : 'You have reached the daily top-up limit. Please try again tomorrow.');
        }
        if ((float) self::counted($user, now()->subDays(7))->sum('price_usd') + $priceUsd > TopupSettings::float('weekly_cap_usd')) {
            throw new TopupException('You have reached the weekly top-up limit.');
        }
        if ((clone $day)->where('created_at', '>=', now()->subHour())->count() >= TopupSettings::int('max_orders_per_hour')) {
            throw new TopupException('Too many top-ups in a short time. Please wait a little and try again.');
        }
        $numbers = (clone $day)->distinct()->pluck('phone_e164');
        if (! $numbers->contains($phoneE164) && $numbers->count() >= TopupSettings::int('max_numbers_per_day')) {
            throw new TopupException('You have topped up the maximum number of different phone numbers for today.');
        }
    }
}
