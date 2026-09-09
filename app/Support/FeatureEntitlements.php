<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The ENFORCEMENT side of white-label feature entitlement (Updater Batch 8).
 * One universal gate, `locked($key)`, wired into every lockable feature — and it
 * runs everywhere, because the white-label forks are byte-for-byte copies of the
 * master. What differs is the DATA, not the code:
 *
 *   - On the MASTER (original) platform it is INERT: the master is the authority,
 *     never a subscriber, so it locks nothing. Guarded twice — by product
 *     identity (never the fork product), and by there simply being no stored lock
 *     list. So every `locked()` call is a cheap `false` and the master's own
 *     users are never gated.
 *   - On a FORK it reads the lock list that fork last received from the master
 *     (the WhiteLabelUpdateClient stores it on check-in, from the master's
 *     entitlement response). No list yet (fresh fork, or master unreachable) →
 *     FAIL OPEN: nothing locked. A network blip or a not-yet-registered instance
 *     must never hard-lock a running app — the same resilience posture as the
 *     Batch 5 pull client.
 *
 * The lock keys are FeatureLocks' catalog (gift_cards, esim_voice,
 * preloader_customization, brand_hunt). FeatureLocks (master authority) decides
 * the list per level; this decides whether a given key is in the list HERE.
 */
class FeatureEntitlements
{
    /** Where a fork persists the lock list it received from the master. */
    public const STORE_KEY = 'white_label.entitlement_locks';

    private const CACHE_KEY = 'white_label.entitlement_locks.local';

    /** The master/original product identifier — never carries feature locks. */
    private const MASTER_PRODUCT = 'naarasim-core';

    /** Is this deployment the original master platform (never gated)? */
    public static function isMaster(): bool
    {
        return (string) config('updater.product_identifier', self::MASTER_PRODUCT) === self::MASTER_PRODUCT;
    }

    /**
     * The lock list in effect on THIS deployment. Empty on the master and on any
     * fork that hasn't received a list yet. Never throws (a missing settings
     * table on a fresh boot must not break a page — same posture as
     * LinkPreviewSettings / NumbersBento).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        if (self::isMaster()) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $stored = Setting::getValue(self::STORE_KEY, []);
            } catch (\Throwable) {
                return [];
            }

            return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
        });
    }

    /** Is this feature locked on this deployment right now? */
    public static function locked(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /** Convenience inverse — reads better at a call site that unlocks. */
    public static function allowed(string $key): bool
    {
        return ! self::locked($key);
    }

    /**
     * A fork persists the lock list it received from the master. Never called on
     * the master (its WhiteLabelUpdateClient doesn't exist), but guarded anyway so
     * the master can't be locked even by a stray write.
     *
     * @param  list<string>  $locks
     */
    public static function store(array $locks): void
    {
        if (self::isMaster()) {
            return;
        }

        Setting::setValue(self::STORE_KEY, array_values(array_filter($locks, 'is_string')), 'white_label');
        self::bust();
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
