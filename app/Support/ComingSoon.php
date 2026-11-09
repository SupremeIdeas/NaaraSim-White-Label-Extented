<?php

namespace App\Support;

/** Slim live-check for the white-label build: a feature is live when the admin switched it on (and, for top-up, a supplier is enabled). Off means a plain 404. */
class ComingSoon
{
    public static function isLive(string $key): bool
    {
        if ($key === 'naara_topup' && FeatureEntitlements::locked(FeatureLocks::F_TOPUP)) {
            return false;   // this licence level does not include top-ups
        }

        return match ($key) {
            'naara_topup' => FeatureFlags::enabled($key) && \App\Models\TopupProvider::where('enabled', true)->exists(),
            default => FeatureFlags::enabled($key),
        };
    }

    public static function abortIfOff(string $key): void
    {
        abort_unless(self::isLive($key), 404);
    }
}
