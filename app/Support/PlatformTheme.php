<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard background / theme system.
 *
 * One admin-selectable mode governs the wallpaper that sits behind the whole
 * authenticated app shell (customer + admin) — never the marketing site or auth
 * pages. Everything is expressed through CSS variables the `.dashboard-bg`
 * stylesheet already understands, so this class only ever emits a small <style>
 * override (never a rebuild) — the same runtime-override pattern as
 * BrandSettings::themeCss(). The default mode emits nothing and gets the
 * built-in premium treatment.
 *
 * Modes: default | static_gradient | animated_gradient | image. Each carries a
 * fully independent light and dark config; a light-mode change derives a
 * sensible dark starting point (deriveDark) but the two stay separately
 * editable after that. All tuning values are clamped HERE (server-side), so no
 * admin input can produce a structurally broken background.
 */
class PlatformTheme
{
    private const CACHE_KEY = 'platform.theme';

    public const SETTING_KEY = 'platform_theme.config';

    public const MODES = ['default', 'static_gradient', 'animated_gradient', 'image'];

    /** Curated brand-derived extension palette for the optional extra stop. */
    public const EXTRA_PALETTE = [
        'primary' => 'var(--brand-primary)',
        'accent' => 'var(--brand-accent)',
        'action' => 'var(--brand-action)',
        'navy' => 'var(--brand-navy)',
    ];

    /** Per-mode default tuning (mirrors the curated CSS in ui-elements.css). */
    public const TUNING_DEFAULTS = [
        'up_intensity' => 1.0,
        'lo_intensity' => 1.0,
        'up_feather' => 82,
        'lo_feather' => 88,
        'up_size' => 65,   // % — width of the upper ellipse (height tracks it)
        'lo_size' => 70,
        'extra_color' => 'primary',
        'extra_alpha' => 0.0,
    ];

    /** @return array{mode:string, animated_contexts:array, light:array, dark:array, image_light:string, image_dark:string} */
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
                'mode' => in_array($cfg['mode'] ?? '', self::MODES, true) ? $cfg['mode'] : 'default',
                'light' => self::sanitizeTuning($cfg['light'] ?? []),
                'dark' => self::sanitizeTuning($cfg['dark'] ?? [], dark: true),
                'image_light' => (string) ($cfg['image_light'] ?? ''),
                'image_dark' => (string) ($cfg['image_dark'] ?? ''),
            ];
        });
    }

    public static function mode(): string
    {
        return self::current()['mode'];
    }

    /** The <body> class list that turns the treatment on (+ motion when animated). */
    public static function bodyClass(): string
    {
        $class = 'dashboard-bg';
        if (self::mode() === 'animated_gradient') {
            $class .= ' dashboard-bg--animated';
        }

        return $class;
    }

    /**
     * The runtime <style> override for the active mode. Empty for `default`
     * (the built-in CSS already renders it). Only ever sets known --dbg-*
     * variables or the ::before wallpaper — no author-supplied CSS reaches the
     * page, so the injection is safe.
     */
    public static function styleCss(): string
    {
        $c = self::current();
        $mode = $c['mode'];

        if ($mode === 'default') {
            return '';
        }

        if ($mode === 'image') {
            return self::imageCss($c['image_light'], $c['image_dark']);
        }

        // static_gradient / animated_gradient share the same colour-stop config;
        // motion is handled by the body class, not here.
        $light = self::tuningVars($c['light']);
        $dark = self::tuningVars($c['dark']);

        $css = '';
        if ($light !== '') {
            $css .= '.dashboard-bg{'.$light.'}';
        }
        if ($dark !== '') {
            $css .= '.dark .dashboard-bg{'.$dark.'}';
        }

        return $css;
    }

    /** Wallpaper mode: swap the ::before for the uploaded image (per theme). */
    private static function imageCss(string $light, string $dark): string
    {
        $css = '';
        if ($light !== '') {
            $css .= ".dashboard-bg::before{background:url('".self::safeUrl($light)."') center/cover no-repeat;}";
        }
        if ($dark !== '') {
            $css .= ".dark .dashboard-bg::before{background:url('".self::safeUrl($dark)."') center/cover no-repeat;}";
        }

        return $css;
    }

    /** Build the `--dbg-*: value;` declarations for one theme's tuning. */
    private static function tuningVars(array $t): string
    {
        $d = self::TUNING_DEFAULTS;
        $lines = [];
        // Only emit values that differ from the curated default (keeps the
        // injected style tiny and lets the built-in CSS own everything else).
        if ((float) $t['up_intensity'] !== (float) $d['up_intensity']) {
            $lines[] = '--dbg-up-intensity:'.self::num($t['up_intensity']).';';
        }
        if ((float) $t['lo_intensity'] !== (float) $d['lo_intensity']) {
            $lines[] = '--dbg-lo-intensity:'.self::num($t['lo_intensity']).';';
        }
        if ((int) $t['up_feather'] !== (int) $d['up_feather']) {
            $lines[] = '--dbg-up-feather:'.(int) $t['up_feather'].'%;';
        }
        if ((int) $t['lo_feather'] !== (int) $d['lo_feather']) {
            $lines[] = '--dbg-lo-feather:'.(int) $t['lo_feather'].'%;';
        }
        if ((int) $t['up_size'] !== (int) $d['up_size']) {
            $lines[] = '--dbg-up-size:'.(int) $t['up_size'].'% '.(int) round($t['up_size'] * 0.85).'%;';
        }
        if ((int) $t['lo_size'] !== (int) $d['lo_size']) {
            $lines[] = '--dbg-lo-size:'.(int) $t['lo_size'].'% '.(int) round($t['lo_size'] * 0.85).'%;';
        }
        if ((float) $t['extra_alpha'] > 0) {
            $lines[] = '--dbg-extra-color:'.self::EXTRA_PALETTE[$t['extra_color']].';';
            $lines[] = '--dbg-extra-alpha:'.self::num($t['extra_alpha']).';';
        }

        return implode('', $lines);
    }

    /**
     * Clamp one theme's tuning to safe ranges (Section 3b). Dark mode's extra
     * stop may be zero (coral-off asymmetry) — the clamps below allow that.
     */
    public static function sanitizeTuning(array $t, bool $dark = false): array
    {
        $d = self::TUNING_DEFAULTS;

        return [
            'up_intensity' => self::clampF($t['up_intensity'] ?? $d['up_intensity'], 0.5, 1.5),
            'lo_intensity' => self::clampF($t['lo_intensity'] ?? $d['lo_intensity'], 0.5, 1.5),
            'up_feather' => self::clampI($t['up_feather'] ?? $d['up_feather'], 70, 92),
            'lo_feather' => self::clampI($t['lo_feather'] ?? $d['lo_feather'], 70, 92),
            'up_size' => self::clampI($t['up_size'] ?? $d['up_size'], 45, 90),
            'lo_size' => self::clampI($t['lo_size'] ?? $d['lo_size'], 45, 90),
            'extra_color' => array_key_exists($t['extra_color'] ?? '', self::EXTRA_PALETTE) ? $t['extra_color'] : $d['extra_color'],
            'extra_alpha' => self::clampF($t['extra_alpha'] ?? $d['extra_alpha'], 0.0, 0.35),
        ];
    }

    /**
     * Derive a coherent dark-mode starting point from a light-mode config (same
     * hue family, rebalanced for a navy base). The admin can override afterwards;
     * this is only the default when they first touch light mode.
     */
    public static function deriveDark(array $light): array
    {
        $light = self::sanitizeTuning($light);

        return self::sanitizeTuning([
            // Teal is pushed up over navy; the lower bloom eased back a touch.
            'up_intensity' => $light['up_intensity'],
            'lo_intensity' => max(0.5, $light['lo_intensity'] * 0.85),
            'up_feather' => $light['up_feather'],
            'lo_feather' => $light['lo_feather'],
            'up_size' => $light['up_size'],
            'lo_size' => $light['lo_size'],
            'extra_color' => $light['extra_color'],
            // Coral/action reads as "off" on navy — dark defaults the extra stop
            // to zero (documented asymmetry) unless the admin re-enables it.
            'extra_alpha' => $light['extra_color'] === 'action' ? 0.0 : $light['extra_alpha'],
        ], dark: true);
    }

    /** Persist a full config (already-clamped on read) and bust the cache. */
    public static function save(array $config): void
    {
        $mode = in_array($config['mode'] ?? '', self::MODES, true) ? $config['mode'] : 'default';
        Setting::setValue(self::SETTING_KEY, [
            'mode' => $mode,
            'light' => self::sanitizeTuning($config['light'] ?? []),
            'dark' => self::sanitizeTuning($config['dark'] ?? [], dark: true),
            'image_light' => (string) ($config['image_light'] ?? ''),
            'image_dark' => (string) ($config['image_dark'] ?? ''),
        ], group: 'platform');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // -- helpers --------------------------------------------------------------

    private static function clampF(mixed $v, float $min, float $max): float
    {
        return round(max($min, min($max, (float) $v)), 3);
    }

    private static function clampI(mixed $v, int $min, int $max): int
    {
        return (int) max($min, min($max, (int) round((float) $v)));
    }

    private static function num(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    }

    /** Only allow a same-origin path or an https URL as a wallpaper source. */
    private static function safeUrl(string $url): string
    {
        $url = trim($url);
        if (! str_starts_with($url, '/') && ! str_starts_with($url, 'https://')) {
            return '';
        }

        // Whitelist safe URL characters only — this strips quotes, parens,
        // semicolons, whitespace and anything else that could break out of the
        // url('') context or inject a second declaration.
        return preg_replace('#[^A-Za-z0-9/._:%?=&~+\-]#', '', $url);
    }
}
