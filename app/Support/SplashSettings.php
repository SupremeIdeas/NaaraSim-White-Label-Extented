<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-configurable opening splash (blueprint Section 24). Reads the splash.*
 * settings from the settings cache so an admin change reflects immediately
 * after a cache bust — never a redeploy. Light + dark logo variants are stored
 * so both themes look right; the no-flash guarantee comes from the pre-paint
 * theme script in the layout head (Section 24.3).
 */
class SplashSettings
{
    public const CACHE_KEY = 'splash.settings';

    /** @return array<string, mixed> */
    public static function current(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, fn () => [
                'enabled' => (bool) Setting::getValue('splash.enabled', false),
                'product_logo_light' => (string) Setting::getValue('splash.product_logo_light', ''),
                'product_logo_dark' => (string) Setting::getValue('splash.product_logo_dark', ''),
                'product_name' => (string) Setting::getValue('splash.product_name', config('app.name', 'NaaraSim')),
                'brand_logo_light' => (string) Setting::getValue('splash.brand_logo_light', ''),
                'brand_logo_dark' => (string) Setting::getValue('splash.brand_logo_dark', ''),
                'brand_tagline' => (string) Setting::getValue('splash.brand_tagline', 'from Supreme Ideas'),
                'duration_ms' => min(4000, (int) Setting::getValue('splash.duration_ms', 1400)),
                'show_once_per_session' => (bool) Setting::getValue('splash.show_once_per_session', true),
            ]);
        } catch (\Throwable $e) {
            // Settings unavailable (pre-install / DB hiccup) — splash off.
            return self::disabledDefaults();
        }
    }

    /** @return array<string, mixed> */
    private static function disabledDefaults(): array
    {
        return [
            'enabled' => false,
            'product_logo_light' => '', 'product_logo_dark' => '',
            'product_name' => config('app.name', 'NaaraSim'),
            'brand_logo_light' => '', 'brand_logo_dark' => '',
            'brand_tagline' => 'from Supreme Ideas',
            'duration_ms' => 1400, 'show_once_per_session' => true,
        ];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Keys this feature owns — used to bust the cache on a settings save. */
    public static function isSplashKey(string $key): bool
    {
        return str_starts_with($key, 'splash.');
    }
}
