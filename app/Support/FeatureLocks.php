<?php

namespace App\Support;

/**
 * The catalog of lockable white-label feature keys, on a white-label build.
 *
 * BOUNDARY (see docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md): the
 * *authority* half of feature entitlement — which features are locked per
 * level, the admin override, the per-level resolution — lives ONLY on the
 * master platform. A white-label instance is a consumer: it receives its
 * resolved lock list from the master (via the activate response / entitlement
 * endpoint, stored by WhiteLabelUpdateClient) and enforces it through
 * App\Support\FeatureEntitlements. So on a fork this class is just the shared
 * vocabulary of key names + their human labels — never the decision of what a
 * given level unlocks. Do not re-add level maps, defaults, or admin-save
 * methods here; that would make a fork an entitlement authority, which the
 * boundary forbids.
 */
class FeatureLocks
{
    // Lockable feature keys (the catalog). The master decides which of these
    // are locked for this instance's level; the fork only needs the names to
    // gate on via FeatureEntitlements::locked(self::F_*).
    public const F_GIFT_CARDS = 'gift_cards';

    public const F_ESIM_VOICE = 'esim_voice'; // full "Naara Connect" calls+SMS eSIM (data-only stays open)

    public const F_PRELOADER = 'preloader_customization';

    public const F_BRAND_HUNT = 'brand_hunt';

    /** @return array<string, string> feature key => human label. */
    public static function catalog(): array
    {
        return [
            self::F_GIFT_CARDS => 'Gift cards',
            self::F_ESIM_VOICE => 'Full voice eSIM (Naara Connect — calls + SMS)',
            self::F_PRELOADER => 'Preloader customization',
            self::F_BRAND_HUNT => 'Brand Hunt / Brand Directory',
        ];
    }
}
