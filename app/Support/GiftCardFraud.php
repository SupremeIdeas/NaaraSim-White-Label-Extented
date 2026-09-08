<?php

namespace App\Support;

use App\Models\GiftCardOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\GiftCards\GiftCardException;

/**
 * Naara Gift fraud controls (gift cards are a known laundering vector — the
 * Zendit prompt makes these non-optional). Purchase velocity limits, a
 * new-account cooling-off, and a manual-review threshold — all admin-configurable
 * (no hardcoded constants). Ties into the one-account policy: limits are per
 * account, which is why multi-accounting is restricted.
 */
class GiftCardFraud
{
    /** @return array<string, int|float> the live, admin-set thresholds. */
    public static function settings(): array
    {
        return [
            'max_amount_24h' => (float) Setting::getValue('giftcards.max_amount_24h', 500),
            'max_count_24h' => (int) Setting::getValue('giftcards.max_count_24h', 6),
            'max_amount_7d' => (float) Setting::getValue('giftcards.max_amount_7d', 2000),
            'cooloff_hours' => (int) Setting::getValue('giftcards.cooloff_hours', 24),
            'cooloff_value' => (float) Setting::getValue('giftcards.cooloff_value', 100),
            'review_threshold' => (float) Setting::getValue('giftcards.review_threshold', 250),
        ];
    }

    /** Orders that count toward velocity (anything not failed/refunded). */
    private static function countedOrders(User $user, \DateTimeInterface $since)
    {
        return GiftCardOrder::where('user_id', $user->id)
            ->whereNotIn('status', [GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED])
            ->where('created_at', '>=', $since);
    }

    /**
     * Enforce the limits for a pending purchase of $retail USD. Throws a
     * GiftCardException the storefront surfaces to the user.
     */
    public static function assert(User $user, float $retail): void
    {
        $s = self::settings();

        // New-account cooling-off: a fresh account can't buy above the cool-off
        // value until it's aged past the window.
        if ($retail > $s['cooloff_value'] && $user->created_at
            && $user->created_at->gt(now()->subHours($s['cooloff_hours']))) {
            throw new GiftCardException('For your security, high-value gift cards are available once your account is a little older. Try a smaller amount for now.');
        }

        // 24h $ + count.
        $day = self::countedOrders($user, now()->subDay());
        if ((float) (clone $day)->sum('price_charged') + $retail > $s['max_amount_24h']) {
            throw new GiftCardException('You have reached the daily gift-card spending limit. Please try again later.');
        }
        if ((clone $day)->count() >= $s['max_count_24h']) {
            throw new GiftCardException('You have reached the daily gift-card purchase limit. Please try again later.');
        }

        // 7-day $.
        $week = self::countedOrders($user, now()->subDays(7));
        if ((float) $week->sum('price_charged') + $retail > $s['max_amount_7d']) {
            throw new GiftCardException('You have reached the weekly gift-card spending limit. Please try again later.');
        }
    }

    /** Whether this purchase must route to the manual review queue first. */
    public static function needsReview(float $retail): bool
    {
        return $retail >= self::settings()['review_threshold'];
    }
}
