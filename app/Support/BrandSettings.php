<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed brand assets (Module 26). Stores the uploaded logo set + brand
 * name so the whole platform re-brands with no redeploy. Each logo has a light
 * and dark variant so both themes look right; the favicon/app icon feeds the
 * browser tab and installable app icon. Values are public URLs (served from the
 * public/Wasabi disk via MediaStorage). Cached; busted on any brand.* save.
 *
 * Everything falls back gracefully to the text wordmark + built-in icon until a
 * logo is uploaded, so a fresh install always looks intentional.
 */
class BrandSettings
{
    private const CACHE_KEY = 'brand.settings';

    /** Setting keys this feature owns (for upload + cache-bust). */
    public const KEYS = [
        'brand.name',
        'brand.word',
        'brand.logo_family_light',
        'brand.logo_family_dark',
        'brand.logo_product_light',
        'brand.logo_product_dark',
        'brand.logo_agency_light',
        'brand.logo_agency_dark',
        'brand.logo_gift_light',
        'brand.logo_gift_dark',
        'brand.favicon',
        'brand.color_primary',
        'brand.color_accent',
        'brand.color_navy',
        'brand.color_action',
        'brand.radius',
        'brand.preloader_enabled',
        'brand.preloader_style',
        'brand.font_display_source',
        'brand.font_display_google',
        'brand.font_display_custom',
        'brand.font_sans_source',
        'brand.font_sans_google',
        'brand.font_sans_custom',
    ];

    /** Sources an admin can pick per font slot ('' = keep the shipped default). */
    public const FONT_SOURCES = ['google', 'custom'];

    /**
     * Fixed CSS font-family names used for a CUSTOM-uploaded font file. Using a
     * code-generated name (rather than trusting admin-supplied text) sidesteps
     * CSS-injection risk entirely for the upload path — only the Google Fonts
     * name is admin-supplied free text, and that is strictly validated.
     */
    public const CUSTOM_FONT_FAMILY = [
        'display' => 'Naara Custom Display',
        'sans' => 'Naara Custom Sans',
    ];

    /** Preloader visual styles the admin can pick (blueprint audit §7). */
    public const PRELOADER_STYLES = ['pulse-logo', 'spinner', 'bars', 'progress'];

    /** Brand default hex palette (mirrors app.css :root — CLAUDE.md Section 2). */
    public const COLOR_DEFAULTS = [
        'primary' => '#0A6E6E',
        'accent' => '#D4A017',
        'navy' => '#0D1B2A',
        'action' => '#E8412A',
    ];

    /**
     * The official brand logos shipped with the product (committed in public/brand,
     * transparent PNG). These are the DEFAULTS — an admin upload (brand.* setting)
     * always wins, but a fresh install renders the real NaaraSim + Supreme Ideas
     * Agency marks out of the box instead of the text wordmark.
     */
    public const LOGO_DEFAULTS = [
        // family = the umbrella "Naara" mark (home dashboard, marketing, Aurora
        // welcome); product = NaaraSim (eSIM + number surfaces); gift = Naara
        // Gift (the gift storefront); agency = Supreme Ideas Agency.
        'family_light' => '/brand/naara-family-light.png',
        'family_dark' => '/brand/naara-family-dark.png',
        'product_light' => '/brand/naarasim-product-light.png',
        'product_dark' => '/brand/naarasim-product-dark.png',
        'gift_light' => '/brand/naara-gift-light.png',
        'gift_dark' => '/brand/naara-gift-dark.png',
        'agency_light' => '/brand/supreme-ideas-light.png',
        'agency_dark' => '/brand/supreme-ideas-dark.png',
        'favicon' => '/brand/naarasim-favicon.png',
    ];

    /** @return array<string, string> */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'name' => (string) (Setting::getValue('brand.name') ?: config('app.name', 'NaaraSim')),
                    // White-label brand WORD — the token swapped into product /
                    // sub-brand names ("Naara Rent" → "{word} Rent"). Default 'Naara'.
                    'word' => (string) (Setting::getValue('brand.word') ?: 'Naara'),
                    'family_light' => (string) Setting::getValue('brand.logo_family_light', ''),
                    'family_dark' => (string) Setting::getValue('brand.logo_family_dark', ''),
                    'product_light' => (string) Setting::getValue('brand.logo_product_light', ''),
                    'product_dark' => (string) Setting::getValue('brand.logo_product_dark', ''),
                    'agency_light' => (string) Setting::getValue('brand.logo_agency_light', ''),
                    'agency_dark' => (string) Setting::getValue('brand.logo_agency_dark', ''),
                    'gift_light' => (string) Setting::getValue('brand.logo_gift_light', ''),
                    'gift_dark' => (string) Setting::getValue('brand.logo_gift_dark', ''),
                    'favicon' => (string) Setting::getValue('brand.favicon', ''),
                    'color_primary' => (string) Setting::getValue('brand.color_primary', ''),
                    'color_accent' => (string) Setting::getValue('brand.color_accent', ''),
                    'color_navy' => (string) Setting::getValue('brand.color_navy', ''),
                    'color_action' => (string) Setting::getValue('brand.color_action', ''),
                    'radius' => (string) Setting::getValue('brand.radius', ''),
                    'preloader_enabled' => (bool) Setting::getValue('brand.preloader_enabled', false),
                    'preloader_style' => (string) Setting::getValue('brand.preloader_style', ''),
                    // Admin-tunable per-logo scale (multiplier). Default 1 = shipped size.
                    'logo_scale_family' => (string) Setting::getValue('brand.logo_scale_family', ''),
                    'logo_scale_product' => (string) Setting::getValue('brand.logo_scale_product', ''),
                    'logo_scale_gift' => (string) Setting::getValue('brand.logo_scale_gift', ''),
                    // Site-wide font override (title + body). '' source = the
                    // shipped Naara default (Supreme Display / Didact Gothic).
                    'font_display_source' => (string) Setting::getValue('brand.font_display_source', ''),
                    'font_display_google' => (string) Setting::getValue('brand.font_display_google', ''),
                    'font_display_custom' => (string) Setting::getValue('brand.font_display_custom', ''),
                    'font_sans_source' => (string) Setting::getValue('brand.font_sans_source', ''),
                    'font_sans_google' => (string) Setting::getValue('brand.font_sans_google', ''),
                    'font_sans_custom' => (string) Setting::getValue('brand.font_sans_custom', ''),
                ];
            } catch (\Throwable) {
                return self::defaults();
            }
        });
    }

    /** @return array<string, string> */
    private static function defaults(): array
    {
        return [
            'name' => config('app.name', 'NaaraSim'),
            'word' => 'Naara',
            'family_light' => '', 'family_dark' => '',
            'product_light' => '', 'product_dark' => '',
            'agency_light' => '', 'agency_dark' => '',
            'gift_light' => '', 'gift_dark' => '',
            'favicon' => '',
            'color_primary' => '', 'color_accent' => '', 'color_navy' => '', 'color_action' => '',
            'radius' => '', 'preloader_enabled' => false, 'preloader_style' => '',
            'font_display_source' => '', 'font_display_google' => '', 'font_display_custom' => '',
            'font_sans_source' => '', 'font_sans_google' => '', 'font_sans_custom' => '',
        ];
    }

    /** A brand colour (hex), admin override or the default. */
    public static function color(string $key): string
    {
        $v = self::current()['color_'.$key] ?? '';

        return $v !== '' ? $v : (self::COLOR_DEFAULTS[$key] ?? '#000000');
    }

    /** The shipped brand word — the token white-label rebrands away from. */
    public const DEFAULT_WORD = 'Naara';

    /** The configured white-label brand word (default 'Naara'). */
    public static function word(): string
    {
        $w = trim((string) (self::current()['word'] ?? ''));

        return $w !== '' ? $w : self::DEFAULT_WORD;
    }

    /** True when a white-label word is set (i.e. not the shipped 'Naara'). */
    public static function isWhiteLabelled(): bool
    {
        return self::word() !== self::DEFAULT_WORD;
    }

    /**
     * Swap the brand token for the configured white-label word in a string:
     * "Naara Rent" → "{word} Rent", "NaaraCredits" → "{word}Credits". Only the
     * capitalised token 'Naara' is matched, so lowercase asset paths
     * (build/naara-x.js) and unrelated text are never touched. A no-op when the
     * word is still the shipped 'Naara', so default installs pay nothing.
     */
    public static function rebrand(?string $text): string
    {
        $text = (string) $text;
        $word = self::word();
        if ($word === self::DEFAULT_WORD || $text === '') {
            return $text;
        }

        return str_replace(self::DEFAULT_WORD, $word, $text);
    }

    /**
     * 25 curated colour palettes offered as one-click presets in Branding, on
     * top of the shipped Naara palette + a full custom override. Each is
     * {primary, accent, navy, action} — chosen for contrast + a warm accent that
     * reads on both themes. Applied sitewide via the existing brand CSS vars.
     *
     * @var array<string, array{primary:string, accent:string, navy:string, action:string}>
     */
    public const PALETTES = [
        'Naara Teal' => ['primary' => '#0A6E6E', 'accent' => '#D4A017', 'navy' => '#0D1B2A', 'action' => '#E8412A'],
        'Midnight Indigo' => ['primary' => '#4F46E5', 'accent' => '#F59E0B', 'navy' => '#111827', 'action' => '#EF4444'],
        'Royal Violet' => ['primary' => '#7C3AED', 'accent' => '#F5B301', 'navy' => '#1E1B2E', 'action' => '#EC4899'],
        'Emerald Forest' => ['primary' => '#047857', 'accent' => '#F59E0B', 'navy' => '#0B1F17', 'action' => '#DC2626'],
        'Ocean Blue' => ['primary' => '#0369A1', 'accent' => '#FBBF24', 'navy' => '#0C1A2B', 'action' => '#F43F5E'],
        'Sunset Coral' => ['primary' => '#E11D48', 'accent' => '#F59E0B', 'navy' => '#1B1113', 'action' => '#FB7185'],
        'Amber Gold' => ['primary' => '#B45309', 'accent' => '#0EA5E9', 'navy' => '#1C1508', 'action' => '#EA580C'],
        'Slate Pro' => ['primary' => '#334155', 'accent' => '#F59E0B', 'navy' => '#0F172A', 'action' => '#0EA5E9'],
        'Crimson Noir' => ['primary' => '#B91C1C', 'accent' => '#FBBF24', 'navy' => '#180B0B', 'action' => '#F97316'],
        'Cyber Lime' => ['primary' => '#3F6212', 'accent' => '#84CC16', 'navy' => '#0E1406', 'action' => '#22D3EE'],
        'Deep Purple' => ['primary' => '#6D28D9', 'accent' => '#22D3EE', 'navy' => '#14101F', 'action' => '#F472B6'],
        'Rose Quartz' => ['primary' => '#BE185D', 'accent' => '#FBBF24', 'navy' => '#1A0E15', 'action' => '#FB7185'],
        'Steel Blue' => ['primary' => '#1D4ED8', 'accent' => '#F59E0B', 'navy' => '#0B1220', 'action' => '#06B6D4'],
        'Jade Mint' => ['primary' => '#0D9488', 'accent' => '#F59E0B', 'navy' => '#0A1A18', 'action' => '#F43F5E'],
        'Copper Rust' => ['primary' => '#9A3412', 'accent' => '#FACC15', 'navy' => '#1A0F08', 'action' => '#DC2626'],
        'Sky Fresh' => ['primary' => '#0284C7', 'accent' => '#FACC15', 'navy' => '#0B1725', 'action' => '#F97316'],
        'Plum Wine' => ['primary' => '#86198F', 'accent' => '#FBBF24', 'navy' => '#170A18', 'action' => '#E11D48'],
        'Olive Earth' => ['primary' => '#4D7C0F', 'accent' => '#EAB308', 'navy' => '#12160A', 'action' => '#EA580C'],
        'Graphite Gold' => ['primary' => '#1F2937', 'accent' => '#D4A017', 'navy' => '#0B0F17', 'action' => '#EF4444'],
        'Turquoise Pop' => ['primary' => '#0891B2', 'accent' => '#F59E0B', 'navy' => '#0A1A1E', 'action' => '#F43F5E'],
        'Berry Bold' => ['primary' => '#9D174D', 'accent' => '#FBBF24', 'navy' => '#180912', 'action' => '#FB923C'],
        'Pine Green' => ['primary' => '#065F46', 'accent' => '#FCD34D', 'navy' => '#08160F', 'action' => '#F87171'],
        'Cobalt Night' => ['primary' => '#1E40AF', 'accent' => '#FACC15', 'navy' => '#0A0F1F', 'action' => '#F97316'],
        'Terracotta' => ['primary' => '#C2410C', 'accent' => '#0D9488', 'navy' => '#1A0F0A', 'action' => '#DC2626'],
        'Monochrome Ink' => ['primary' => '#111827', 'accent' => '#6B7280', 'navy' => '#030712', 'action' => '#2563EB'],
    ];

    public static function radius(): string
    {
        $v = self::current()['radius'] ?? '';

        return $v !== '' ? $v : '0.5rem';
    }

    public static function preloaderEnabled(): bool
    {
        return (bool) (self::current()['preloader_enabled'] ?? false);
    }

    /** Admin-tunable size multiplier for a logo variant (0.5–2.0; default 1.0). */
    public static function logoScale(string $variant): float
    {
        // Gift/agency use their own key; product + family have theirs; anything
        // else falls back to 1.0 (no scaling).
        $key = in_array($variant, ['family', 'product', 'gift'], true) ? $variant : null;
        if ($key === null) {
            return 1.0;
        }
        $v = (float) (self::current()['logo_scale_'.$key] ?? 0);

        return $v > 0 ? max(0.5, min(2.0, $v)) : 1.0;
    }

    /** The chosen preloader style; defaults to the pulsing logo (audit §7). */
    public static function preloaderStyle(): string
    {
        $v = (string) (self::current()['preloader_style'] ?? '');

        return in_array($v, self::PRELOADER_STYLES, true) ? $v : 'pulse-logo';
    }

    /** True once the admin has overridden any colour or the radius. */
    public static function hasThemeOverride(): bool
    {
        $c = self::current();

        return filled($c['color_primary'] ?? '') || filled($c['color_accent'] ?? '')
            || filled($c['color_navy'] ?? '') || filled($c['color_action'] ?? '')
            || filled($c['radius'] ?? '');
    }

    /**
     * The runtime override CSS injected into the layout head. Emits only the
     * :root brand variables that differ from the defaults, as channel triples
     * so Tailwind's rgb(var(--brand-*) / <alpha>) colours keep working.
     * Returns '' when nothing is customised (no wasted <style>).
     */
    public static function themeCss(): string
    {
        if (! self::hasThemeOverride()) {
            return '';
        }

        $lines = [];
        $map = [
            'color_primary' => 'brand-primary',
            'color_accent' => 'brand-accent',
            'color_navy' => 'brand-navy',
            'color_action' => 'brand-action',
        ];
        $c = self::current();
        foreach ($map as $settingKey => $cssVar) {
            $hex = $c[$settingKey] ?? '';
            if ($hex !== '' && ($rgb = self::hexToChannels($hex)) !== null) {
                $lines[] = "--{$cssVar}: {$rgb};";
                if ($cssVar === 'brand-primary') {
                    $lines[] = '--brand-primary-dark: '.self::darken($hex).';';
                }
            }
        }
        if (($c['radius'] ?? '') !== '') {
            $lines[] = '--brand-radius: '.self::sanitizeRadius($c['radius']).';';
        }

        return $lines === [] ? '' : ':root{'.implode('', $lines).'}';
    }

    /** "#0A6E6E" => "10 110 110" (channel triple), or null if malformed. */
    public static function hexToChannels(string $hex): ?string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return hexdec(substr($hex, 0, 2)).' '.hexdec(substr($hex, 2, 2)).' '.hexdec(substr($hex, 4, 2));
    }

    /** A ~20% darker channel triple for the primary-dark hover token. */
    private static function darken(string $hex): string
    {
        $rgb = self::hexToChannels($hex);
        if ($rgb === null) {
            return '8 85 85';
        }

        return implode(' ', array_map(fn ($c) => (int) round((int) $c * 0.78), explode(' ', $rgb)));
    }

    /** Clamp the radius to a safe rem value (defends the injected CSS). */
    private static function sanitizeRadius(string $radius): string
    {
        return preg_match('/^\d?\.?\d+rem$/', trim($radius)) ? trim($radius) : '0.5rem';
    }

    /**
     * A Google Font family name is free text an admin types in, but it flows
     * into both a CSS font-family string and a fonts.googleapis.com query
     * parameter — letters/digits/spaces only, so it can never break out of
     * either context.
     */
    public static function isValidGoogleFontName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9 ]{1,60}$/', $name);
    }

    /** The admin's chosen source for a font slot ('display'|'sans'), or ''. */
    public static function fontSource(string $slot): string
    {
        $v = (string) (self::current()['font_'.$slot.'_source'] ?? '');

        return in_array($v, self::FONT_SOURCES, true) ? $v : '';
    }

    /** The validated Google Font name configured for a slot, or null. */
    public static function googleFontName(string $slot): ?string
    {
        $name = trim((string) (self::current()['font_'.$slot.'_google'] ?? ''));

        return ($name !== '' && self::isValidGoogleFontName($name)) ? $name : null;
    }

    /** The uploaded custom font file URL configured for a slot, or null. */
    public static function customFontUrl(string $slot): ?string
    {
        $url = (string) (self::current()['font_'.$slot.'_custom'] ?? '');

        return $url !== '' ? $url : null;
    }

    /** True when either slot is actively overridden (Google or custom). */
    public static function hasFontOverride(): bool
    {
        foreach (['display', 'sans'] as $slot) {
            $source = self::fontSource($slot);
            if ($source === 'google' && self::googleFontName($slot) !== null) {
                return true;
            }
            if ($source === 'custom' && self::customFontUrl($slot) !== null) {
                return true;
            }
        }

        return false;
    }

    /** True when at least one slot is sourced from Google Fonts (CSP gate). */
    public static function usesGoogleFont(): bool
    {
        foreach (['display', 'sans'] as $slot) {
            if (self::fontSource($slot) === 'google' && self::googleFontName($slot) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Distinct Google Font names currently in use, across both slots. */
    public static function googleFontFamilies(): array
    {
        $names = [];
        foreach (['display', 'sans'] as $slot) {
            if ($slot === 'sans' && self::fontSource('display') === 'google') {
                $prev = self::googleFontName('display');
                if ($prev !== null && $prev === self::googleFontName('sans')) {
                    continue; // same family requested twice — one <link> is enough.
                }
            }
            if (self::fontSource($slot) === 'google' && ($name = self::googleFontName($slot)) !== null) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /** The fonts.googleapis.com stylesheet URL to <link>, or null if unused. */
    public static function googleFontsHref(): ?string
    {
        $families = self::googleFontFamilies();
        if ($families === []) {
            return null;
        }

        $parts = array_map(
            fn (string $name) => 'family='.str_replace(' ', '+', $name).':wght@400;500;700',
            $families
        );

        return 'https://fonts.googleapis.com/css2?'.implode('&', $parts).'&display=swap';
    }

    /** font-format for a custom-uploaded font file, by its extension. */
    private static function customFontFormat(string $url): string
    {
        return match (strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION))) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2',
        };
    }

    /**
     * The runtime font override CSS injected into the layout head: @font-face
     * rules for any custom upload, plus the :root --font-display/--font-sans
     * variables app.css reads. Returns '' when nothing is overridden (no
     * wasted <style>) — an unconfigured install keeps the shipped Naara fonts.
     */
    public static function fontCss(): string
    {
        if (! self::hasFontOverride()) {
            return '';
        }

        $faces = [];
        $vars = [];
        foreach (['display', 'sans'] as $slot) {
            $source = self::fontSource($slot);
            if ($source === 'custom' && ($url = self::customFontUrl($slot)) !== null) {
                $family = self::CUSTOM_FONT_FAMILY[$slot];
                $format = self::customFontFormat($url);
                $faces[] = "@font-face{font-family:'{$family}';src:url('{$url}') format('{$format}');font-weight:400 900;font-display:swap;}";
                $vars[] = "--font-{$slot}: '{$family}';";
            } elseif ($source === 'google' && ($name = self::googleFontName($slot)) !== null) {
                $vars[] = "--font-{$slot}: '{$name}';";
            }
        }

        if ($vars === []) {
            return '';
        }

        return implode('', $faces).':root{'.implode('', $vars).'}';
    }

    public static function name(): string
    {
        return self::current()['name'];
    }

    /**
     * The admin-uploaded logo URL for a variant/theme, or null if none. The
     * per-variant value is intentionally NOT defaulted here — the light<->dark
     * cross-fallback and the shipped-default fallback live in <x-brand-logo> /
     * resolvedLogo(), so a single admin upload still serves both themes.
     */
    public static function logo(string $variant, string $theme): ?string
    {
        $key = $variant.'_'.$theme; // e.g. product_light
        $url = self::current()[$key] ?? '';

        return $url !== '' ? $url : null;
    }

    /**
     * Display URL for a variant/theme: admin upload for that theme → admin
     * upload for the other theme → the shipped brand default. Always returns a
     * URL, so the real logo shows out of the box and any admin upload wins.
     */
    public static function resolvedLogo(string $variant, string $theme): ?string
    {
        $other = $theme === 'light' ? 'dark' : 'light';

        return self::logo($variant, $theme)
            ?? self::logo($variant, $other)
            ?? (self::LOGO_DEFAULTS[$variant.'_'.$theme] ?? null);
    }

    /** True — a product logo always displays (admin upload or the shipped default). */
    public static function hasProductLogo(): bool
    {
        return self::resolvedLogo('product', 'light') !== null;
    }

    public static function favicon(): ?string
    {
        $f = self::current()['favicon'] ?? '';

        return $f !== '' ? $f : self::LOGO_DEFAULTS['favicon'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isBrandKey(string $key): bool
    {
        return str_starts_with($key, 'brand.');
    }
}
