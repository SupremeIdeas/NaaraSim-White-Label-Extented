<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Theme Toggle Studio — admin-selectable visual style for the site-wide
 * dark/light mode switch (`<x-theme-toggle>`). One single choke point: every
 * header/login/UI-kit usage renders through that one component, which
 * resolves the current preset here, so changing the style here changes it
 * everywhere at once.
 *
 * Zero regression: until an admin saves a selection, current() returns
 * DEFAULT — the original RiccardoRapelli sun/moon switch, byte-identical to
 * what every existing install already renders.
 *
 * Behavioural contract is shared by every preset partial and never changes:
 * a real checkbox drives an Alpine `dark` boolean, which is persisted to
 * localStorage, toggles `.dark` on <html>, and dispatches `theme-changed`.
 * Only the markup/CSS differs per preset.
 *
 * Owner request (2026-09-15) — 2 of the 5 presets (Eclipse Orb, Day/Night
 * Dial) are master-only, the same `master_only`/availablePresets() pattern
 * Preloader Studio already uses for its "favorite" presets: the code and
 * CSS ship identically to every white-label fork (byte-for-byte, per this
 * codebase's whole distribution model), but availablePresets() is what the
 * Studio picker and save() actually enforce against, so a fork simply never
 * sees or can select the two master-exclusive styles.
 */
class ThemeToggleSettings
{
    private const KEY = 'brand.theme_toggle.style';

    private const CACHE_KEY = 'brand.theme_toggle.style.resolved';

    public const DEFAULT = 'sun-moon';

    /**
     * @var array<string, array{label:string, category:string, master_only?:bool}>
     */
    public const PRESETS = [
        'sun-moon' => ['label' => 'Sun & Moon', 'category' => 'Classic'],
        'eclipse-orb' => ['label' => 'Eclipse Orb', 'category' => '3D', 'master_only' => true],
        'day-night-dial' => ['label' => 'Day/Night Dial', 'category' => 'Realistic', 'master_only' => true],
        'aurora-pill' => ['label' => 'Aurora Pill', 'category' => 'Minimal'],
        'horizon-track' => ['label' => 'Horizon Track', 'category' => 'Minimal'],
    ];

    /**
     * PRESETS filtered to what THIS deployment may pick from — the master
     * platform gets all 5; a white-label fork gets the 3 that aren't
     * `master_only`. Mirrors PreloaderSettings::availablePresets() exactly.
     *
     * @return array<string, array{label:string, category:string, master_only?:bool}>
     */
    public static function availablePresets(): array
    {
        if (FeatureEntitlements::isMaster()) {
            return self::PRESETS;
        }

        return array_filter(self::PRESETS, fn (array $preset) => empty($preset['master_only']));
    }

    /** The style slug currently in effect (Setting-backed, cached, safe default). */
    public static function current(): string
    {
        $slug = Cache::remember(self::CACHE_KEY, 3600, function () {
            try {
                return (string) Setting::getValue(self::KEY, self::DEFAULT);
            } catch (\Throwable) {
                return self::DEFAULT;
            }
        });

        return isset(self::PRESETS[$slug]) ? $slug : self::DEFAULT;
    }

    public static function save(string $slug): void
    {
        $slug = isset(self::availablePresets()[$slug]) ? $slug : self::DEFAULT;
        Setting::setValue(self::KEY, $slug, 'branding', 'Theme toggle switch style');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
