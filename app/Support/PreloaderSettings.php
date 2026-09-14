<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Preloader Studio (BLUEPRINT-preloader-studio.md) — brand-aware, per-page-type
 * preloader assignment on top of the existing single-choke-point preloader.
 *
 * Design rules enforced here:
 *  - Portability: page types are domain-agnostic. The system ships knowing only
 *    the BUILT_IN_TYPES; feature code teaches it about `numbers`, `gifts`, etc.
 *    via registerPageType() in a service provider boot() — never hardcoded here.
 *  - Zero regression: until an admin actively saves an assignment, forPageType()
 *    returns exactly what BrandSettings exposes today (enabled + legacy style),
 *    so the live preloader is unchanged bit-for-bit on every existing install.
 *  - No hardcoded colours in presets: colours resolve to CSS custom properties
 *    with brand-token fallbacks, so a Branding colour change re-tints every
 *    preset with no per-assignment resave.
 *
 * Storage (Setting keys, encrypted JSON like every other setting):
 *  - brand.preloader.page_types   → { "numbers": "Numbers & Virtual Lines", ... }
 *  - brand.preloader.assignments  → { "default": {..cfg..}, "checkout": {"inherit":true}, ... }
 */
class PreloaderSettings
{
    private const CACHE_TYPES = 'brand.preloader.page_types';

    private const CACHE_ASSIGN = 'brand.preloader.assignments';

    private const KEY_TYPES = 'brand.preloader.page_types';

    private const KEY_ASSIGN = 'brand.preloader.assignments';

    /**
     * Domain-agnostic page types shipped by the preloader system itself. Feature
     * modules register their own via registerPageType(); those are persisted and
     * merged in at runtime. Keep this list free of feature-specific slugs.
     *
     * @var array<string, string>
     */
    public const BUILT_IN_TYPES = [
        'default' => 'Default (all pages)',
        'dashboard' => 'Dashboard & account',
        'marketing' => 'Marketing site',
        'admin' => 'Admin panel',
        'auth' => 'Sign-in & sign-up',
        'checkout' => 'Checkout & payment',
    ];

    /**
     * The legacy styles that predate the Studio. They keep rendering through the
     * original <x-brand-preloader> markup branches, so existing Branding-page
     * selections never break. They are also selectable in the Studio.
     */
    public const LEGACY_STYLES = ['pulse-logo', 'spinner', 'bars', 'progress'];

    /** Blur intensities — shared vocabulary with the glass-header control. */
    public const BLUR_STYLES = ['light' => 6, 'medium' => 12, 'heavy' => 20];

    /** Size multipliers for the S/M/L presets (custom px handled separately). */
    public const SIZES = ['sm' => 0.75, 'md' => 1.0, 'lg' => 1.4];

    /**
     * The ported preset catalog. `c` = how many brandable colour slots the
     * preset exposes (--nx-pl-c1..cN). Flags:
     *   heavy        → GPU-expensive; flagged in the picker + saveData-guarded.
     *   allow_neutral→ exposes a "use white/neutral instead of brand" toggle.
     *   text         → exposes an editable loading-text string.
     *   favorite     → Supreme Ideas curated set (grouped separately in the UI).
     *
     * @var array<string, array{label:string, category:string, complexity:string, c:int, heavy?:bool, allow_neutral?:bool, text?:bool, favorite?:bool}>
     */
    public const PRESETS = [
        'equalizer' => ['label' => 'Equalizer Bars', 'category' => 'Bars', 'complexity' => 'Light', 'c' => 1],
        'wifi-rings' => ['label' => 'Signal Rings', 'category' => 'Rings', 'complexity' => 'Light', 'c' => 3, 'text' => true],
        'corner-beams' => ['label' => 'Corner Beams', 'category' => 'Sweep', 'complexity' => 'Light', 'c' => 1],
        'crystals' => ['label' => 'Crystal Prisms', 'category' => '3D', 'complexity' => 'Medium', 'c' => 2],
        'svg-rings' => ['label' => 'Orbit Rings', 'category' => 'Rings', 'complexity' => 'Medium', 'c' => 4],
        'cube-pulse' => ['label' => 'Pulsing Cubes', 'category' => '3D', 'complexity' => 'Light', 'c' => 2],
        'dot-blast' => ['label' => 'Dot Blast', 'category' => 'Dots', 'complexity' => 'Light', 'c' => 1, 'allow_neutral' => true],
        'dot-orbit-3d' => ['label' => '3D Dot Orbit', 'category' => 'Dots', 'complexity' => 'Medium', 'c' => 2],
        'simple-pulse' => ['label' => 'Simple Pulse', 'category' => 'Dots', 'complexity' => 'Trivial', 'c' => 1],
        'heart-square' => ['label' => 'Heartbeat', 'category' => 'Morph', 'complexity' => 'Medium', 'c' => 1],
        'dot-grid' => ['label' => 'Dot Grid', 'category' => 'Dots', 'complexity' => 'Light', 'c' => 1],
        'sphere-wave' => ['label' => 'Sphere Wave', 'category' => 'SVG', 'complexity' => 'Heavy', 'c' => 5, 'heavy' => true],
        'progress-text' => ['label' => 'Progress Text', 'category' => 'Text', 'complexity' => 'Light', 'c' => 1, 'text' => true],
        'worm-ring' => ['label' => 'Worm Ring', 'category' => 'Rings', 'complexity' => 'Medium', 'c' => 2],
        // Supreme Ideas curated favorites.
        'letters' => ['label' => 'Generating Letters', 'category' => 'Text', 'complexity' => 'Medium', 'c' => 1, 'text' => true, 'favorite' => true],
        'spokes' => ['label' => 'Spoke Spinner', 'category' => 'Rings', 'complexity' => 'Light', 'c' => 1, 'favorite' => true],
        'goo-dots' => ['label' => 'Liquid Dots', 'category' => 'Dots', 'complexity' => 'Medium', 'c' => 3, 'favorite' => true],
        'neon-rings' => ['label' => 'Neon Rings', 'category' => 'Rings', 'complexity' => 'Heavy', 'c' => 1, 'heavy' => true, 'favorite' => true],
        'fintech-breath' => ['label' => 'Fintech Breath', 'category' => 'SVG', 'complexity' => 'Medium', 'c' => 1, 'favorite' => true],
        'flying-files' => ['label' => 'Flying Files', 'category' => 'Sweep', 'complexity' => 'Medium', 'c' => 2, 'favorite' => true],
    ];

    /**
     * PRESETS filtered to what THIS deployment may pick from. The `favorite`
     * (Supreme Ideas premium curated) subset is the original platform's alone
     * — see docs/ui-component-library/premium-preloaders-MASTER-ONLY.md — so a
     * white-label fork (byte-for-byte copy of this same code) never sees or
     * can select them, even though their CSS/partials ship with the codebase
     * like everything else. Single source of truth for the Studio picker AND
     * its save-time validation, so the boundary can't be bypassed by a direct
     * component call that skips the picker UI.
     *
     * @return array<string, array{label:string, category:string, complexity:string, c:int, heavy?:bool, allow_neutral?:bool, text?:bool, favorite?:bool}>
     */
    public static function availablePresets(): array
    {
        if (FeatureEntitlements::isMaster()) {
            return self::PRESETS;
        }

        return array_filter(self::PRESETS, fn (array $preset) => empty($preset['favorite']));
    }

    /** Hard safe default — matches today's behaviour when nothing is configured. */
    public static function safeDefault(): array
    {
        return [
            'preset' => BrandSettings::preloaderStyle(), // legacy style (pulse-logo by default)
            'enabled' => BrandSettings::preloaderEnabled(),
            'use_brand_color' => true,
            'use_neutral' => false,
            'size' => 'md',
            'speed' => 1.0,
            'opacity' => 1.0,
            'bg_color' => null,
            'bg_opacity' => 1.0,
            'blur' => false,
            'blur_style' => 'medium',
            'loading_text' => 'Loading',
            'colors' => [], // manual --nx-pl-c* overrides when use_brand_color=false
        ];
    }

    /**
     * The full page-type map (built-ins + feature-registered), for the picker.
     *
     * @return array<string, string>
     */
    public static function pageTypes(): array
    {
        $registered = Cache::rememberForever(self::CACHE_TYPES, function () {
            try {
                $v = Setting::getValue(self::KEY_TYPES, []);

                return is_array($v) ? $v : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return array_merge(self::BUILT_IN_TYPES, $registered);
    }

    /**
     * Register (persist) a feature page type so the admin picker always shows it.
     * Idempotent; safe to call every boot(). No-ops on a not-yet-migrated DB.
     */
    public static function registerPageType(string $slug, string $label): void
    {
        $slug = self::slug($slug);
        if ($slug === '' || array_key_exists($slug, self::BUILT_IN_TYPES)) {
            return;
        }
        try {
            $current = Setting::getValue(self::KEY_TYPES, []);
            $current = is_array($current) ? $current : [];
            if (($current[$slug] ?? null) === $label) {
                return; // already registered, unchanged
            }
            $current[$slug] = $label;
            Setting::setValue(self::KEY_TYPES, $current, 'branding', 'Preloader page types');
            Cache::forget(self::CACHE_TYPES);
        } catch (\Throwable) {
            // DB not ready (install/migrate) — silently skip; re-registers next boot.
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function assignments(): array
    {
        return Cache::rememberForever(self::CACHE_ASSIGN, function () {
            try {
                $v = Setting::getValue(self::KEY_ASSIGN, []);

                return is_array($v) ? $v : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Resolve the preloader config for a page type, following the inherit chain
     * to `default` and falling back to the hard safe default (== today's
     * behaviour) when nothing is saved or a row is corrupt.
     */
    public static function forPageType(string $type): array
    {
        $type = self::slug($type) ?: 'default';
        $all = self::assignments();

        // Unconfigured: preserve current production behaviour exactly.
        if ($all === []) {
            return self::safeDefault();
        }

        $row = $all[$type] ?? null;
        // Missing row, or explicit inherit → fall through to default.
        if (! is_array($row) || ! empty($row['inherit'])) {
            $row = $all['default'] ?? null;
        }
        if (! is_array($row) || ! empty($row['inherit'])) {
            return self::safeDefault();
        }

        $cfg = array_merge(self::safeDefault(), $row);

        // Defend the preset slug: must be a known legacy style or catalog preset.
        if (! in_array($cfg['preset'], self::LEGACY_STYLES, true) && ! isset(self::PRESETS[$cfg['preset']])) {
            $cfg['preset'] = self::safeDefault()['preset'];
        }

        return $cfg;
    }

    /** True when the resolved preset is one of the legacy (pre-Studio) styles. */
    public static function isLegacy(string $preset): bool
    {
        return in_array($preset, self::LEGACY_STYLES, true);
    }

    /**
     * Build the inline `style="..."` custom-property string for a resolved cfg.
     * Only emits colour overrides when the admin turned brand colours OFF, so
     * the brand-token fallbacks in each preset partial stay authoritative.
     */
    public static function resolveCssVars(array $cfg): string
    {
        $out = [];

        // Size → scale multiplier.
        $scale = self::SIZES[$cfg['size']] ?? null;
        if ($scale === null && is_numeric($cfg['size'])) {
            // custom px against a ~64px baseline
            $scale = max(0.4, min(3.0, ((float) $cfg['size']) / 64));
        }
        $out['--nx-pl-scale'] = number_format($scale ?? 1.0, 3, '.', '');

        // Speed multiplier (durations are calc(base / var)).
        $speed = (float) ($cfg['speed'] ?? 1.0);
        $out['--nx-pl-speed'] = number_format(max(0.25, min(4.0, $speed)), 2, '.', '');

        // Foreground opacity.
        $out['--nx-pl-opacity'] = number_format(max(0.0, min(1.0, (float) ($cfg['opacity'] ?? 1.0))), 2, '.', '');

        // Blur (px) — 0 when disabled.
        $blurPx = ! empty($cfg['blur']) ? (self::BLUR_STYLES[$cfg['blur_style']] ?? 12) : 0;
        $out['--nx-pl-blur'] = $blurPx.'px';

        // Manual colours (only when brand colours are off). Neutral flag forces white.
        if (empty($cfg['use_brand_color'])) {
            if (! empty($cfg['use_neutral'])) {
                $out['--nx-pl-c1'] = '255 255 255';
            } else {
                foreach (($cfg['colors'] ?? []) as $i => $hex) {
                    $rgb = BrandSettings::hexToChannels((string) $hex);
                    if ($rgb !== null) {
                        $out['--nx-pl-c'.((int) $i + 1)] = $rgb;
                    }
                }
            }
        } elseif (! empty($cfg['use_neutral'])) {
            // Brand colours on but neutral explicitly chosen (dot-blast case).
            $out['--nx-pl-c1'] = '255 255 255';
        }

        $css = '';
        foreach ($out as $k => $v) {
            $css .= $k.':'.$v.';';
        }

        return $css;
    }

    /** Loading text for the text-capable presets, clamped to a sane length. */
    public static function loadingText(array $cfg): string
    {
        $t = trim((string) ($cfg['loading_text'] ?? 'Loading'));

        return $t === '' ? 'Loading' : mb_substr($t, 0, 24);
    }

    /** Persist a page-type assignment (full cfg object). */
    public static function saveAssignment(string $type, array $cfg): void
    {
        $type = self::slug($type) ?: 'default';
        $all = self::assignments();
        $all[$type] = $cfg;
        Setting::setValue(self::KEY_ASSIGN, $all, 'branding', 'Preloader per-page assignments');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_TYPES);
        Cache::forget(self::CACHE_ASSIGN);
    }

    private static function slug(string $s): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($s))) ?? '';
    }
}
