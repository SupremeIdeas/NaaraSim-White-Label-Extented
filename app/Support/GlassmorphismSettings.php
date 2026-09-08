<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-tunable intensity for the site-wide `.nx-glass-tile` card surface
 * (owner request — the desktop side menu, eSIM catalogue cards and similar
 * "wide" containers read as too solid/old-fashioned; wants a premium,
 * lightweight glassmorphism feel with an admin dial for opacity + blur).
 *
 * Deliberately does NOT touch `.nx-glass`/`.nx-card` (already-blurred
 * hero/feature surfaces) — those keep their own fixed treatment. Also never
 * applies to: the More-menu sheet (blur there previously caused GPU
 * compositing artifacts mid-transition on Android, AND is a separate owner
 * decision — "SOLID cards in both themes" — see app-shell.blade.php), the
 * Numbers section (its own bespoke dark-gradient look, explicitly "perfect,
 * don't touch"), and the NaaraSim support-chat widget (also explicitly
 * "perfect, don't touch"). Applying `.nx-glass-tile` is a per-component
 * choice made in the blade files that use it — this class only controls how
 * strong the effect looks WHEREVER it's already applied.
 *
 * Same runtime-CSS-variable-override pattern as PlatformTheme/ThemePreset: a
 * default/untouched install emits nothing and renders exactly the values
 * already hardcoded in ui-elements.css's `.nx-glass-tile` rule (kept in sync
 * with DEFAULT_OPACITY/DEFAULT_BLUR below — see that file for the shipped
 * fallback values the CSS `var(..., fallback)` uses).
 */
class GlassmorphismSettings
{
    private const CACHE_KEY = 'platform.glass.v1';

    public const SETTING_KEY = 'platform.glass_cards';

    /** Shipped defaults — MUST match ui-elements.css's `.nx-glass-tile` var()
     *  fallbacks so an admin who never touches this setting sees zero change. */
    public const DEFAULT_OPACITY = 62; // % — light-mode fill opacity

    public const DEFAULT_BLUR = 12; // px

    /** Dark-mode opacity is always this many points lower than light (the
     *  shipped ratio: 62% light / 55% dark) — one shared "how solid" dial
     *  rather than two, per the owner's request for a single control. */
    private const DARK_OFFSET = 7;

    public const MIN_OPACITY = 20;

    public const MAX_OPACITY = 100;

    public const MIN_BLUR = 0;

    public const MAX_BLUR = 24;

    /** @return array{opacity: int, blur: int} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $cfg = Setting::getValue(self::SETTING_KEY);
            } catch (\Throwable) {
                $cfg = null;
            }
            $cfg = is_array($cfg) ? $cfg : [];

            return [
                'opacity' => self::clamp((int) ($cfg['opacity'] ?? self::DEFAULT_OPACITY), self::MIN_OPACITY, self::MAX_OPACITY),
                'blur' => self::clamp((int) ($cfg['blur'] ?? self::DEFAULT_BLUR), self::MIN_BLUR, self::MAX_BLUR),
            ];
        });
    }

    public static function opacity(): int
    {
        return self::current()['opacity'];
    }

    public static function blur(): int
    {
        return self::current()['blur'];
    }

    /**
     * The `:root{--nx-glass-*}` override — empty (nothing injected) when both
     * values are still the shipped defaults, so an untouched install adds
     * zero extra CSS, exactly like PlatformTheme/ThemePreset.
     */
    public static function styleCss(): string
    {
        $c = self::current();
        if ($c['opacity'] === self::DEFAULT_OPACITY && $c['blur'] === self::DEFAULT_BLUR) {
            return '';
        }

        $lightAlpha = self::num($c['opacity'] / 100);
        $darkAlpha = self::num(max(self::MIN_OPACITY, $c['opacity'] - self::DARK_OFFSET) / 100);

        return ":root{--nx-glass-opacity-light:{$lightAlpha};--nx-glass-opacity-dark:{$darkAlpha};--nx-glass-blur:{$c['blur']}px;}";
    }

    public static function save(int $opacity, int $blur): void
    {
        Setting::setValue(self::SETTING_KEY, [
            'opacity' => self::clamp($opacity, self::MIN_OPACITY, self::MAX_OPACITY),
            'blur' => self::clamp($blur, self::MIN_BLUR, self::MAX_BLUR),
        ], group: 'platform');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0';
    }
}
