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
 */
class ThemeToggleSettings
{
    private const KEY = 'brand.theme_toggle.style';

    private const CACHE_KEY = 'brand.theme_toggle.style.resolved';

    public const DEFAULT = 'sun-moon';

    /**
     * @var array<string, array{label:string, category:string}>
     */
    public const PRESETS = [
        'sun-moon' => ['label' => 'Sun & Moon', 'category' => 'Classic'],
        'eclipse-orb' => ['label' => 'Eclipse Orb', 'category' => '3D'],
        'day-night-dial' => ['label' => 'Day/Night Dial', 'category' => 'Realistic'],
        'aurora-pill' => ['label' => 'Aurora Pill', 'category' => 'Minimal'],
        'horizon-track' => ['label' => 'Horizon Track', 'category' => 'Minimal'],
    ];

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
        $slug = isset(self::PRESETS[$slug]) ? $slug : self::DEFAULT;
        Setting::setValue(self::KEY, $slug, 'branding', 'Theme toggle switch style');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
