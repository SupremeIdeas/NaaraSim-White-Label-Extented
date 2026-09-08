<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * NAARA THEME SYSTEM — Batch 1 §1.2. The switchable-skin engine, a direct
 * sibling of PlatformTheme.
 *
 * A theme is PAINT, never plumbing. This class only ever emits a small,
 * strictly-whitelisted <style> block of `:root` CSS-variable overrides and a
 * single `theme-{slug}` body class — the exact same runtime-override discipline
 * BrandSettings::themeCss() and PlatformTheme::styleCss() already use. No
 * business logic, no Livewire fork, no route touches a theme. The built-in
 * `naara-official` look already ships in app.css, so its override is the empty
 * string — selecting it is a true no-op visually.
 *
 * Security property (the single most important one): admins can add or edit
 * themes, but NO theme, however it is stored, can inject arbitrary CSS or
 * script. styleCss() emits only known variable names, and every token value is
 * re-validated at read time against a strict schema (channel-triple colours,
 * bounded radii, an enumerated font allow-list). Anything out of range is
 * dropped, not emitted.
 */
class ThemePreset
{
    private const CACHE_KEY = 'theme.active.v1';

    /** Setting key that stores the active preset's slug. */
    public const SETTING_KEY = 'platform.active_theme';

    /** The permanent default + fallback slug — its CSS override is always empty. */
    public const DEFAULT_SLUG = 'naara-official';

    /**
     * Every colour token an admin may override (owner request, 2026-09-07:
     * "advance settings to change each color with color code... reset to
     * default color"). Same 6 keys emitVars() already knows how to turn
     * into CSS custom properties — this constant is the shared whitelist
     * between the resolver (merging overrides into tokens.colors) and the
     * admin editor (which fields to render, in this order).
     */
    public const COLOR_KEYS = ['primary', 'primary_dark', 'accent', 'accent_dark', 'navy', 'action'];

    /**
     * Header editor (owner request, 2026-09-07: "full control" over the
     * header — colour, corner curve, glass depth, independent of the
     * theme's own brand colours). Bottom-corner radius is 10-40px, clamped
     * server-side; it never applies to the 'default' header style (the
     * naara-official fade/blur bar has no bottom edge to round). Blur
     * ("glassmorphism depth") applies to every header including 'default',
     * so naara-official can gain glass depth it never shipped with.
     */
    public const HEADER_RADIUS_MIN = 10;

    public const HEADER_RADIUS_MAX = 40;

    public const HEADER_BLUR_MIN = 0;

    public const HEADER_BLUR_MAX = 24;

    /**
     * Each header style's built-in blur (Tailwind's own scale: plain
     * `backdrop-blur`=8px, `backdrop-blur-md`=12px) — the value a header
     * keeps rendering until an admin actually dials the glass-depth slider,
     * so shipping this feature is a zero-regression change for every
     * existing theme.
     */
    private const HEADER_BLUR_DEFAULTS = [
        'default' => 0,
        'aries-contrast' => 0,
        'midnight-signal' => 8,
        'neon-vertex' => 8,
        'paperwhite' => 0,
        'origin-bold' => 0,
        'solar-flare' => 0,
        'noir-reserve' => 12,
        // Batch 3 (2026-09-07): none of these 5 personas ship with a
        // hardcoded backdrop-blur — an admin can still dial one in.
        'aurora-shift' => 0,
        'sunset-transit' => 0,
        'fintra-clean' => 0,
        'capable-mono' => 0,
        'waitlisty-soft' => 0,
    ];

    /** The three structural layout partials a page may pick between. */
    public const VARIANTS = ['variant-a', 'variant-b', 'variant-c'];

    /** Valid icon_family.style values (Theme Installer Batch 3 — shared with iconFamily()'s own read-time fallback). */
    public const ICON_STYLES = ['3d', 'sprite'];

    /** icon_family.set pattern — shared by iconFamily() and the Theme Installer's install-time validation. */
    public const ICON_SET_PATTERN = '/^[a-z0-9\-]{1,40}$/';

    /** hero_assets value pattern — shared by heroFor() and the Theme Installer's post-copy URL check. */
    public const HERO_ASSET_PATTERN = '#^(/[\w./-]+|https?://[\w./:?=&%-]+)$#';

    /**
     * Swappable CHROME sections (owner request, 2026-09-07): unlike
     * layout_variants (a page's own content), these are shared UI shells —
     * header, bottom nav, login screen, landing hero — that any preset,
     * including naara-official, can point at any named style family. Only
     * 'default' exists today (today's exact, unchanged markup); batch theme
     * builds add more named families here as they ship, and an admin picks
     * per-section per-theme once there's a real choice to make. Never a raw
     * path or arbitrary string — always resolved through this whitelist, so
     * a corrupt/tampered row can only ever fall back to 'default', never
     * reach an @include with attacker-controlled input.
     */
    public const SECTION_STYLE_ALLOW = [
        'header' => [
            'default',
            // Batch 1 of the theme visual rebuild (2026-09-07) — one named
            // style family per theme, keyed by that theme's own slug (a
            // future batch can point TWO themes at the same key to share a
            // style; batch 1 gives each its own to maximise the requested
            // "very unique, don't look identical" variety).
            'aries-contrast', 'midnight-signal', 'neon-vertex', 'paperwhite', 'origin-bold',
            // Batch 2 (2026-09-07): 2 brand-new personas join with their own
            // header, alongside batch 1's 3 completing their full suite below.
            'solar-flare', 'noir-reserve',
            // Batch 3 (2026-09-07): 5 brand-new personas, built from scratch.
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft',
        ],
        'bottom_nav' => [
            'default',
            'aries-contrast', 'midnight-signal', 'neon-vertex', 'paperwhite', 'origin-bold',
            'solar-flare', 'noir-reserve',
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft',
        ],
        'login' => [
            'default',
            'aries-contrast', 'midnight-signal', 'neon-vertex', 'paperwhite', 'origin-bold',
            'solar-flare', 'noir-reserve',
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft',
        ],
        // A decorative layer independent of login STRUCTURE (owner request:
        // "some login bg will have custom unique dot grid material effects
        // and mesh grain on some, Aurora bg") — any login style family,
        // including 'default', can be paired with any of these. 'none' is
        // the only default so every existing theme keeps today's exact
        // background until an admin deliberately assigns an effect.
        'login_bg' => ['none', 'dot-grid', 'mesh-grain', 'aurora'],
        // Each entry here is a genuinely unique, hand-built landing page for
        // ONE theme (owner request, 2026-09-07) — never a generic template —
        // with its own admin-editable content fields registered in
        // LandingHeroLibrary. 'default' keeps using the existing site-wide
        // homepage content (SiteContent/PageBuilder), untouched.
        // Batch 2 (2026-09-07): aries-contrast/paperwhite/origin-bold
        // complete their full suite (they already have header/bottom_nav/
        // login from batch 1); solar-flare/noir-reserve are brand new.
        'landing_hero' => ['default', 'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve', 'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft'],
        // The rest of a theme's "full suite" (owner request, 2026-09-07):
        // About, How It Works, Contact — each theme's own version of these
        // pages, at the same content depth as naara-official's own (hero +
        // several full sections, not a stub), registered in
        // ThemePageLibrary. Pricing is deliberately NOT themed here — it's a
        // real Livewire component with live pricing logic
        // (Admin\PricingPage), not a content page, so forking its whole
        // layout per theme is a much bigger, riskier undertaking than a
        // content page reskin.
        'about_page' => ['default', 'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve', 'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft'],
        'how_it_works_page' => ['default', 'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve', 'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft'],
        'contact_page' => ['default', 'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve', 'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft'],
        // Footer (owner request, 2026-09-07: "please all themes too should
        // have unique footer too... not always we get a straight line
        // footer"). Chrome-only, like header/bottom_nav — no editable
        // content fields, just a visual/structural reskin of the SAME
        // functional content (SiteChrome columns/legal, socials, app-export
        // slot). 'default' keeps today's exact shared footer everywhere
        // else, including naara-official.
        'footer' => ['default', 'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve', 'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft'],
    ];

    /**
     * Font families a theme is allowed to name. Every value must ALSO be
     * registered in tailwind.config.js + loaded via @font-face / Google Fonts
     * at build time — never injected per-theme at runtime (FOUT/CLS risk,
     * CLAUDE.md §S24). Batch 2 extends this list once, up front, with the 15
     * presets' fonts; Batch 1 ships only what the current look already uses.
     */
    private const FONT_ALLOW = [
        'Supreme Display', 'Didact Gothic', 'Figtree',
    ];

    /**
     * The active preset, array-shaped (tokens/icon_family/hero_assets/
     * layout_variants already decoded). Cached forever, busted only on admin
     * save. Fails safe to a synthetic naara-official on any error (missing
     * table on a fresh install, corrupted row, mid-migration) — exactly
     * PlatformTheme::current()'s try/catch discipline.
     *
     * @return array{slug:string,name:string,persona:?string,tokens:array,icon_family:array,hero_assets:array,layout_variants:array,section_styles:array,is_built_in:bool}
     */
    public static function active(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $slug = Setting::getValue(self::SETTING_KEY) ?: self::DEFAULT_SLUG;
                $row = DB::table('theme_presets')->where('slug', $slug)->first();

                // Missing active row → fall back to the built-in, never blank.
                if ($row === null) {
                    $row = DB::table('theme_presets')->where('slug', self::DEFAULT_SLUG)->first();
                }
                if ($row === null) {
                    return self::synthetic();
                }

                return [
                    'slug' => (string) $row->slug,
                    'name' => (string) $row->name,
                    'persona' => $row->persona,
                    'tokens' => self::mergeColorOverrides(self::decode($row->tokens), self::decode($row->color_overrides ?? null)),
                    'icon_family' => self::decode($row->icon_family),
                    'hero_assets' => self::decode($row->hero_assets),
                    'layout_variants' => self::decode($row->layout_variants),
                    'section_styles' => self::decode($row->section_styles ?? null),
                    'landing_content' => self::decode($row->landing_content ?? null),
                    'page_content' => self::decode($row->page_content ?? null),
                    'header_settings' => self::decode($row->header_settings ?? null),
                    'is_built_in' => (bool) $row->is_built_in,
                ];
            } catch (\Throwable) {
                return self::synthetic();
            }
        });
    }

    public static function slug(): string
    {
        return self::active()['slug'];
    }

    /** @return array the decoded, un-validated token bag (styleCss validates on emit). */
    public static function tokens(): array
    {
        return self::active()['tokens'];
    }

    public static function iconFamily(): array
    {
        $fam = self::active()['icon_family'];

        return [
            'style' => in_array($fam['style'] ?? null, self::ICON_STYLES, true) ? $fam['style'] : '3d',
            'set' => is_string($fam['set'] ?? null) && preg_match(self::ICON_SET_PATTERN, $fam['set']) ? $fam['set'] : 'default',
        ];
    }

    /** A single body class the CSS and any presentational @class can key off. */
    public static function bodyClass(): string
    {
        return 'theme-'.self::slug();
    }

    /**
     * The active theme's hero image for a surface ('dashboard'|'esim'|'numbers'),
     * or null if it has none. Each preset carries its own hero art in
     * hero_assets, so applying a theme swaps the home hero. Only same-origin
     * paths / http(s) URLs pass — never arbitrary strings reaching an <img src>.
     */
    public static function heroFor(string $surface): ?string
    {
        $val = self::active()['hero_assets'][$surface] ?? null;
        if (! is_string($val) || $val === '') {
            return null;
        }

        return preg_match(self::HERO_ASSET_PATTERN, $val) === 1 ? $val : null;
    }

    /**
     * Which structural partial a given page uses under the active theme.
     * Defaults to variant-a (the extracted current markup) for any page/theme
     * combination not explicitly set, so a missing key never 500s.
     */
    public static function layoutVariant(string $page): string
    {
        $pick = self::active()['layout_variants'][$page] ?? null;

        return in_array($pick, self::VARIANTS, true) ? $pick : 'variant-a';
    }

    /**
     * Which named style family a swappable chrome SECTION uses under the
     * active theme (see SECTION_STYLE_ALLOW). Falls back to that section's
     * own neutral value — by convention always the FIRST entry in its
     * SECTION_STYLE_ALLOW array ('default' for structural sections,
     * 'none' for the decorative login_bg layer) — for any section/theme
     * combination not explicitly assigned, or any value outside that
     * section's whitelist, so a missing or tampered key never 500s and
     * never reaches an @include with unvalidated input. An unrecognised
     * $section (not in SECTION_STYLE_ALLOW at all) falls back to the
     * literal 'default', since it has no array of its own to draw from.
     */
    public static function sectionStyle(string $section): string
    {
        return self::sectionStyleFor(self::active()['section_styles'], $section);
    }

    /**
     * The same whitelist resolution as sectionStyle(), but for an arbitrary
     * (already-decoded) section_styles bag rather than the cached active
     * theme — lets the admin header editor resolve "what header style does
     * THIS theme card use" for any theme, not just whichever one happens to
     * be active right now.
     *
     * @param  array<string, mixed>  $sectionStyles
     */
    public static function sectionStyleFor(array $sectionStyles, string $section): string
    {
        $allow = self::SECTION_STYLE_ALLOW[$section] ?? null;
        if ($allow === null || $allow === []) {
            return 'default';
        }

        $pick = $sectionStyles[$section] ?? null;

        return in_array($pick, $allow, true) ? $pick : $allow[0];
    }

    /**
     * The active theme's per-theme landing-page content (owner request,
     * 2026-09-07) — only meaningful when sectionStyle('landing_hero') isn't
     * 'default'. Returns [] for 'default' (nothing to render through this
     * system; the existing SiteContent/PageBuilder homepage content applies
     * as normal). For a custom style, every field declared in
     * LandingHeroLibrary::fieldsFor() is resolved to the admin-saved value
     * — re-validated against that field's OWN type here, exactly like
     * sectionStyle()'s whitelist discipline — or that field's own default
     * when unset/invalid, so a corrupt row can never inject an arbitrary
     * image URL or an out-of-whitelist select value into the page.
     *
     * @return array<string, mixed>
     */
    public static function landingContent(): array
    {
        $style = self::sectionStyle('landing_hero');
        if ($style === 'default') {
            return [];
        }

        $saved = self::active()['landing_content'] ?? [];
        $content = [];

        foreach (LandingHeroLibrary::fieldsFor($style) as $field) {
            $key = $field['key'];
            $value = $saved[$key] ?? null;

            $content[$key] = match ($field['type']) {
                'image' => (is_string($value) && $value !== '' && preg_match('#^(/[\w./-]+|https?://[\w./:?=&%-]+)$#', $value) === 1)
                    ? $value : $field['default'],
                'select' => (is_string($value) && array_key_exists($value, $field['options'] ?? []))
                    ? $value : $field['default'],
                default => (is_string($value) && $value !== '') ? $value : $field['default'],
            };
        }

        return $content;
    }

    /**
     * The active theme's content for one of the OTHER full-suite pages
     * (about_page/how_it_works_page/contact_page — owner request,
     * 2026-09-07), generalized off ThemePageLibrary the same way
     * landingContent() is generalized off LandingHeroLibrary. Kept as a
     * separate method (rather than refactoring landingContent() to share
     * this one) deliberately — landingContent() already shipped and is
     * covered by its own tests; duplicating ~15 lines here is a much safer
     * trade than touching working, tested code under this scope of change.
     *
     * @return array<string, mixed>
     */
    public static function pageContent(string $page): array
    {
        $style = self::sectionStyle($page);
        if ($style === 'default') {
            return [];
        }

        $saved = self::active()['page_content'][$page] ?? [];
        $content = [];

        foreach (ThemePageLibrary::fieldsFor($page, $style) as $field) {
            $key = $field['key'];
            $value = $saved[$key] ?? null;

            $content[$key] = match ($field['type']) {
                'image' => (is_string($value) && $value !== '' && preg_match('#^(/[\w./-]+|https?://[\w./:?=&%-]+)$#', $value) === 1)
                    ? $value : $field['default'],
                'select' => (is_string($value) && array_key_exists($value, $field['options'] ?? []))
                    ? $value : $field['default'],
                default => (is_string($value) && $value !== '') ? $value : $field['default'],
            };
        }

        return $content;
    }

    /**
     * The single shared "Apple-inspired" dark palette every non-default theme
     * falls back to in dark mode. Deliberately NOT derived from each preset's
     * own light-mode tokens — the owner explicitly asked for one consistent,
     * professionally-designed dark look across all 19 presets rather than a
     * per-theme derived one, so the rest of the chrome (sidebar, bottom nav,
     * card surfaces via --brand-navy) reads as one coherent dark UI instead of
     * clashing per-theme tints. naara-official is untouched — this block never
     * emits for it (see the early return above).
     */
    private const DARK_STANDARD = [
        '--brand-primary' => '10 132 255',
        '--brand-primary-dark' => '4 94 199',
        '--brand-accent' => '255 159 10',
        '--brand-navy' => '18 18 20',
        '--brand-action' => '255 69 58',
    ];

    /**
     * The runtime <style> body. EMPTY for the built-in naara-official (app.css
     * already renders that look — no diff, no regression). For every other
     * theme, emit the whitelisted `:root` variable overrides for LIGHT mode
     * (each value re-validated here so nothing arbitrary can reach the page),
     * plus a second, higher-specificity rule that collapses dark mode to the
     * one shared standard palette above — pure CSS cascade, no JS/PHP dark
     * detection needed since `.dark` is a client-toggled class on <html>.
     */
    public static function styleCss(): string
    {
        $preset = self::active();
        if ($preset['is_built_in'] || $preset['slug'] === self::DEFAULT_SLUG) {
            return '';
        }

        $vars = self::emitVars($preset['tokens']);
        $css = $vars === '' ? '' : ':root{'.$vars.'}';

        $darkVars = implode(';', array_map(fn ($k, $v) => "{$k}:{$v}", array_keys(self::DARK_STANDARD), self::DARK_STANDARD)).';';
        $css .= ':root.dark body.theme-'.$preset['slug'].'{'.$darkVars.'}';

        return $css;
    }

    /**
     * The header editor's runtime <style> body — deliberately a SEPARATE
     * emitter from styleCss(), which early-returns '' for naara-official.
     * The header editor must work on naara-official too (owner request:
     * "its own header must have its own unique settings... we can only
     * change the color and also give it its glassmorism depth like the
     * rest but not buttom left and right curve"), so this method never
     * early-returns on is_built_in/DEFAULT_SLUG.
     *
     * Scoped with `body:not(.is-admin-surface)` — app-shell.blade.php (and
     * therefore every header/{style}.blade.php partial) is shared between
     * the customer layout and the admin panel layout, so without this
     * scope a header override would leak into /adminmaster's own chrome.
     * Specificity of that scope (0,2,1) already beats every existing
     * header background rule (plain classes at (0,1,0), dark: compounds at
     * (0,2,0)), so no `!important` is needed.
     */
    public static function headerStyleCss(): string
    {
        $preset = self::active();
        $style = self::sectionStyle('header');
        $settings = self::resolveHeaderSettings($preset['header_settings'] ?? [], $style);

        $scope = 'body:not(.is-admin-surface) [data-header-root]';
        $rules = ["backdrop-filter:blur({$settings['blur']}px)"];
        if ($style !== 'default') {
            $rules[] = "border-bottom-left-radius:{$settings['radius_bl']}px";
            $rules[] = "border-bottom-right-radius:{$settings['radius_br']}px";
        }
        $css = $scope.'{'.implode(';', $rules).'}';

        if ($settings['bg'] !== null) {
            $css .= $scope.'{background:rgb('.$settings['bg'].')}';
            $css .= '.dark '.$scope.'{background:rgb('.$settings['bg'].')}';
        }

        return $css;
    }

    /**
     * The header colour override, as a hex string, or null when the header
     * is using its own theme's default background — used to also sync
     * `<meta name="theme-color">` (the mobile browser chrome tint) so a
     * custom header colour extends all the way to the OS status bar, not
     * just the in-page header.
     */
    public static function headerColorHex(): ?string
    {
        $preset = self::active();
        $style = self::sectionStyle('header');
        $bg = self::resolveHeaderSettings($preset['header_settings'] ?? [], $style)['bg'];

        return $bg === null ? null : self::channelTripleToHex($bg);
    }

    /**
     * Resolve a theme's raw stored header_settings into effective, bounded
     * values — the single source of truth shared by headerStyleCss() (for
     * the active theme) and the admin editor (for whichever theme card is
     * being edited, active or not). Never trusts the stored bag: every
     * field is re-validated against its own bounds here, exactly like
     * mergeColorOverrides()'s discipline for colour overrides.
     *
     * @param  array<string, mixed>  $stored
     * @return array{bg: ?string, radius_bl: int, radius_br: int, blur: int}
     */
    public static function resolveHeaderSettings(array $stored, string $headerStyle): array
    {
        $bg = $stored['bg'] ?? null;
        $bg = self::validChannelTriple($bg) ? $bg : null;

        $radiusBl = 0;
        $radiusBr = 0;
        if ($headerStyle !== 'default') {
            $radiusBl = self::validHeaderRadius($stored['radius_bl'] ?? null) ? (int) $stored['radius_bl'] : 0;
            $radiusBr = self::validHeaderRadius($stored['radius_br'] ?? null) ? (int) $stored['radius_br'] : 0;
        }

        $blurDefault = self::HEADER_BLUR_DEFAULTS[$headerStyle] ?? 0;
        $blur = self::validHeaderBlur($stored['blur'] ?? null) ? (int) $stored['blur'] : $blurDefault;

        return ['bg' => $bg, 'radius_bl' => $radiusBl, 'radius_br' => $radiusBr, 'blur' => $blur];
    }

    private static function validHeaderRadius(mixed $v): bool
    {
        return is_numeric($v) && (int) $v >= self::HEADER_RADIUS_MIN && (int) $v <= self::HEADER_RADIUS_MAX;
    }

    private static function validHeaderBlur(mixed $v): bool
    {
        return is_numeric($v) && (int) $v >= self::HEADER_BLUR_MIN && (int) $v <= self::HEADER_BLUR_MAX;
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The current font allow-list (Theme Installer Batch 3). A live accessor,
     * not a copy — the installer's font-warning check must always compare
     * against whatever this list actually is right now, so it never drifts
     * out of sync when a future code update extends FONT_ALLOW.
     *
     * @return list<string>
     */
    public static function fontAllowList(): array
    {
        return self::FONT_ALLOW;
    }

    /**
     * Public wrapper around the private channel-triple validator (Theme
     * Installer Batch 3) — lets untrusted, not-yet-saved theme data (an
     * uploaded package's tokens.colors, before it ever reaches the database)
     * be checked with the exact same rule emitVars() enforces at render time,
     * so a preview screen never has to guess at, or duplicate, that pattern.
     */
    public static function isValidColorTriple(mixed $v): bool
    {
        return self::validChannelTriple($v);
    }

    /**
     * The browser-chrome colour (mobile address bar / task-switcher card) for
     * the active theme, as a hex string — or null on the built-in default,
     * where the admin's App Export `theme_color` still applies unchanged.
     * Owner request: switching a Theme Preset should repaint the browser
     * chrome too, not just the in-app surfaces.
     */
    public static function browserThemeColor(): ?string
    {
        $preset = self::active();
        if ($preset['is_built_in'] || $preset['slug'] === self::DEFAULT_SLUG) {
            return null;
        }

        $primary = $preset['tokens']['colors']['primary'] ?? null;
        if (! self::validChannelTriple($primary)) {
            return null;
        }

        [$r, $g, $b] = array_map('intval', explode(' ', $primary));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Every preset, for the admin picker (Batch 2). Ordered by sort_order. */
    public static function all(): Collection
    {
        try {
            return collect(DB::table('theme_presets')->orderBy('sort_order')->get())
                ->map(fn ($r) => [
                    'slug' => (string) $r->slug,
                    'name' => (string) $r->name,
                    'persona' => $r->persona,
                    'tokens' => self::mergeColorOverrides(self::decode($r->tokens), self::decode($r->color_overrides ?? null)),
                    'color_overrides' => self::decode($r->color_overrides ?? null),
                    'header_settings' => self::decode($r->header_settings ?? null),
                    'icon_family' => self::decode($r->icon_family),
                    'section_styles' => self::decode($r->section_styles ?? null),
                    'is_built_in' => (bool) $r->is_built_in,
                    'sort_order' => (int) $r->sort_order,
                ]);
        } catch (\Throwable) {
            return collect([self::synthetic()]);
        }
    }

    /**
     * Build the whitelisted `--var:value;` string from a token bag. Only known
     * keys are considered and each value must pass its validator or it is
     * silently dropped — the injection-safety guarantee.
     */
    private static function emitVars(array $tokens): string
    {
        $out = [];

        // Colours — channel triples ("R G B"), reused by Tailwind's
        // rgb(var(--brand-*) / <alpha>) pattern with zero config changes.
        $colorMap = [
            'primary' => '--brand-primary',
            'primary_dark' => '--brand-primary-dark',
            'accent' => '--brand-accent',
            'accent_dark' => '--brand-accent-dark',
            'navy' => '--brand-navy',
            'action' => '--brand-action',
        ];
        foreach ($colorMap as $key => $var) {
            $val = $tokens['colors'][$key] ?? null;
            if (self::validChannelTriple($val)) {
                $out[] = "{$var}:{$val}";
            }
        }

        // Radius — bounded length values. `control` also drives the existing
        // --brand-radius so today's buttons/inputs re-round with no Blade edit.
        $radiusMap = [
            'control' => '--radius-control',
            'card' => '--radius-card',
            'pill' => '--radius-pill',
        ];
        foreach ($radiusMap as $key => $var) {
            $val = $tokens['radius'][$key] ?? null;
            if (self::validLength($val)) {
                $out[] = "{$var}:{$val}";
                if ($key === 'control') {
                    $out[] = "--brand-radius:{$val}";
                }
            }
        }

        // Typography — enumerated font names only (must be tailwind-registered).
        $typoMap = ['display' => '--font-display', 'sans' => '--font-sans'];
        foreach ($typoMap as $key => $var) {
            $val = $tokens['typography'][$key] ?? null;
            if (is_string($val) && in_array($val, self::FONT_ALLOW, true)) {
                // Quote the family name; only allow-listed values reach here.
                $out[] = "{$var}:'{$val}'";
            }
        }

        // Surface — a single bounded numeric opacity for card borders.
        $borderOpacity = $tokens['surface']['card_border_opacity'] ?? null;
        if (self::validUnitInterval($borderOpacity)) {
            $out[] = "--card-border-opacity:{$borderOpacity}";
        }

        return $out === [] ? '' : implode(';', $out).';';
    }

    private static function validChannelTriple(mixed $v): bool
    {
        return is_string($v) && preg_match('/^\d{1,3} \d{1,3} \d{1,3}$/', $v) === 1
            && collect(explode(' ', $v))->every(fn ($n) => (int) $n >= 0 && (int) $n <= 255);
    }

    private static function validLength(mixed $v): bool
    {
        // e.g. 0.5rem, 1.5rem, 12px, 9999px, 0 — bounded, no calc()/expressions.
        return is_string($v) && preg_match('/^(0|\d{1,4}(\.\d{1,3})?(px|rem|em))$/', $v) === 1;
    }

    private static function validUnitInterval(mixed $v): bool
    {
        return (is_string($v) || is_numeric($v)) && preg_match('/^(0(\.\d{1,3})?|1(\.0{1,3})?)$/', (string) $v) === 1;
    }

    /** @return array decoded json, always an array. */
    private static function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** The synthetic built-in used when the table/row isn't available yet. */
    private static function synthetic(): array
    {
        return [
            'slug' => self::DEFAULT_SLUG,
            'name' => 'Naara Official',
            'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons.',
            'tokens' => [],
            'icon_family' => ['style' => '3d', 'set' => 'default'],
            'hero_assets' => [],
            'layout_variants' => [],
            'section_styles' => [],
            'landing_content' => [],
            'page_content' => [],
            'color_overrides' => [],
            'header_settings' => [],
            'is_built_in' => true,
        ];
    }

    /**
     * Overlay an admin's colour overrides onto the theme's seeded palette —
     * only whitelisted COLOR_KEYS are considered, and each override must
     * still pass the same validChannelTriple() check emitVars() uses, so a
     * corrupt/tampered override can never inject an arbitrary CSS value any
     * more than a corrupt seeded token could. Never mutates the original
     * default: an override missing/invalid for a key just leaves that
     * key's seeded value in place — "reset to default" is simply removing
     * the key from color_overrides upstream, nothing to reverse here.
     */
    private static function mergeColorOverrides(array $tokens, array $overrides): array
    {
        foreach (self::COLOR_KEYS as $key) {
            $override = $overrides[$key] ?? null;
            if (self::validChannelTriple($override)) {
                $tokens['colors'][$key] = $override;
            }
        }

        return $tokens;
    }

    /**
     * Hex ("#rrggbb" or "rrggbb") -> the "R G B" channel-triple format every
     * colour token is stored in. Returns null for anything that isn't a
     * strict 6-digit hex colour — the admin editor's own input validation,
     * independent of (and in addition to) mergeColorOverrides()'s own check
     * on the way back out.
     */
    public static function hexToChannelTriple(string $hex): ?string
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return null;
        }

        return implode(' ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }

    /** The reverse of hexToChannelTriple() — for pre-filling the admin editor's colour inputs. */
    public static function channelTripleToHex(string $triple): string
    {
        $parts = array_map('intval', explode(' ', $triple));
        if (count($parts) !== 3) {
            return '#000000';
        }

        return '#'.implode('', array_map(fn ($n) => str_pad(dechex(max(0, min(255, $n))), 2, '0', STR_PAD_LEFT), $parts));
    }
}
