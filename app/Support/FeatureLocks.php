<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\WhiteLabelInstance;
use Illuminate\Support\Facades\Cache;

/**
 * The MASTER-side authority for white-label feature entitlement (Updater Batch 8).
 * Defines which features can be locked, and — per entitlement level — which are
 * locked by default, all admin-overridable without a migration. The master
 * resolves an instance's lock list here and hands it to the fork (via the
 * activate response + the entitlement endpoint); the fork enforces it through
 * App\Support\FeatureEntitlements.
 *
 * Two distinct concerns, kept apart on purpose:
 *   - THIS class = the authority: the catalog + the per-level lists + admin edits.
 *     It answers "what does a `basic` instance have locked?" It runs on the master.
 *   - FeatureEntitlements = the enforcement: it answers "is feature X locked on
 *     THIS deployment right now?" It runs everywhere (inert on the master).
 *
 * Owner spec (2026-09-08): a Normal white-label at `basic` can still sell
 * data-only eSIM + numbers, but gift cards, full voice eSIM, preloader
 * customization and Brand Hunt are locked until it pays up to `standard`
 * (gift cards stay locked there); an Extended white-label (`full`) has nothing
 * locked. All of that is just the SEED — the admin edits each level's list.
 */
class FeatureLocks
{
    private const CACHE_KEY = 'white_label.feature_locks.resolved';

    private const SETTING_KEY = 'white_label.feature_locks';

    // Lockable feature keys (the catalog). Kept small and meaningful — the admin
    // toggles these per level, they aren't free text.
    public const F_GIFT_CARDS = 'gift_cards';

    public const F_ESIM_VOICE = 'esim_voice'; // full "Naara Connect" calls+SMS eSIM (data-only stays open)

    public const F_PRELOADER = 'preloader_customization';

    public const F_BRAND_HUNT = 'brand_hunt';

    /** @return array<string, string> feature key => human label (for the admin UI). */
    public static function catalog(): array
    {
        return [
            self::F_GIFT_CARDS => 'Gift cards',
            self::F_ESIM_VOICE => 'Full voice eSIM (Naara Connect — calls + SMS)',
            self::F_PRELOADER => 'Preloader customization',
            self::F_BRAND_HUNT => 'Brand Hunt / Brand Directory',
        ];
    }

    /**
     * Shipped defaults per level — richer level, fewer locks (strictly nested,
     * so raising a fork's level only ever UNLOCKS). Admin overrides layer on top.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        return [
            WhiteLabelInstance::LEVEL_BASIC => [
                self::F_GIFT_CARDS, self::F_ESIM_VOICE, self::F_PRELOADER, self::F_BRAND_HUNT,
            ],
            WhiteLabelInstance::LEVEL_STANDARD => [
                self::F_GIFT_CARDS,
            ],
            WhiteLabelInstance::LEVEL_FULL => [],
        ];
    }

    /**
     * The admin-effective lock map (defaults merged with the admin's saved
     * override), keyed by level. Cached; every value is filtered back down to the
     * known catalog so a stale/renamed key in a saved override can never leak
     * through as a phantom lock.
     *
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $defaults = self::defaults();
            $catalog = array_keys(self::catalog());

            try {
                $saved = Setting::getValue(self::SETTING_KEY, null);
            } catch (\Throwable) {
                $saved = null;
            }

            $out = [];
            foreach (WhiteLabelInstance::LEVELS as $level) {
                $list = is_array($saved) && isset($saved[$level]) && is_array($saved[$level])
                    ? $saved[$level]
                    : $defaults[$level];

                // Keep only real catalog keys, de-duplicated, order-stable.
                $out[$level] = array_values(array_intersect($catalog, array_values(array_unique($list))));
            }

            return $out;
        });
    }

    /**
     * The locked-feature keys for a given entitlement level. A null/unknown level
     * is treated as "nothing locked" (fail-open) — an instance that never had a
     * level issued must never be surprise-locked.
     *
     * @return list<string>
     */
    public static function locksFor(?string $level): array
    {
        if ($level === null || ! in_array($level, WhiteLabelInstance::LEVELS, true)) {
            return [];
        }

        return self::all()[$level] ?? [];
    }

    /**
     * The entitlement payload the master hands a fork (in the activate response
     * and the entitlement endpoint): the fork's current level plus the resolved
     * lock list for it. One shape, one source of truth, both callers use this.
     *
     * @return array{level: ?string, locks: list<string>}
     */
    public static function entitlementFor(?string $level): array
    {
        return ['level' => $level, 'locks' => self::locksFor($level)];
    }

    /** Persist an admin override for one level (validated against the catalog). */
    public static function saveLevel(string $level, array $lockedKeys): void
    {
        if (! in_array($level, WhiteLabelInstance::LEVELS, true)) {
            return;
        }

        $catalog = array_keys(self::catalog());
        $clean = array_values(array_intersect($catalog, array_values(array_unique($lockedKeys))));

        $saved = Setting::getValue(self::SETTING_KEY, []);
        $saved = is_array($saved) ? $saved : [];
        $saved[$level] = $clean;

        Setting::setValue(self::SETTING_KEY, $saved, 'white_label');
        self::bust();
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
