<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Optional premium hero background for the customer dashboard (owner request).
 * The admin uploads a LIGHT and a DARK image (aurora/wave art, WebP or JPG);
 * the dashboard hero renders whichever matches the active theme, under a
 * gradient overlay so the heading/components still read cleanly. When nothing is
 * uploaded the hero keeps its default look — this is purely additive.
 *
 * Recommended asset: 1600×500 (16:5), WebP q75–80 (~60–140 KB). The hero band is
 * a fixed 16:5 aspect that `cover`s the image, so ONE asset stays crisp and
 * adds zero extra layout height on phone, tablet and desktop.
 */
class HeroBackground
{
    private const CACHE_KEY = 'dashboard.hero.v4';

    public const LIGHT_KEY = 'dashboard.hero.image_light';

    public const DARK_KEY = 'dashboard.hero.image_dark';

    /** Short line under the hero title (BUILD-13 §3). */
    public const DESC_KEY = 'dashboard.hero.description';

    /** Admin on/off switch for the dashboard hero image (owner request). */
    public const ENABLED_KEY = 'dashboard.hero.enabled';

    /** Admin-overridable hero headline (owner request). */
    public const TITLE_KEY = 'dashboard.hero.title';

    /** Admin-chosen headline size preset — see TITLE_SIZES. */
    public const TITLE_SIZE_KEY = 'dashboard.hero.title_size';

    /** Admin-chosen CTA (Buy eSIM / Get Number) size preset — see CTA_SIZES. */
    public const CTA_SIZE_KEY = 'dashboard.hero.cta_size';

    public const DEFAULT_DESCRIPTION = 'Your eSIMs, numbers, and wallet — all in one place.';

    public const DEFAULT_TITLE = 'My Connectivity';

    public const DEFAULT_TITLE_SIZE = 'md';

    public const DEFAULT_CTA_SIZE = 'md';

    /**
     * Headline size preset keys → admin-facing label. 'md' is the size the hero
     * shipped with, so picking it changes nothing.
     *
     * The actual Tailwind classes for each preset are NOT stored here — they
     * live as literal strings in _hero.blade.php's `match()`. Tailwind's content
     * scanner only reads resources/**\/*.blade.php (see tailwind.config.js), not
     * app/**\/*.php, so any class string built only inside a PHP support class
     * would silently vanish from the production build (purged, never generated).
     * This is the same reason ThemePreset emits CSS-variable overrides instead
     * of composing utility classes — keep that discipline here too.
     *
     * @var array<string, string>
     */
    public const TITLE_SIZES = [
        'sm' => 'Small',
        'md' => 'Medium (default)',
        'lg' => 'Large',
        'xl' => 'Extra large',
    ];

    /**
     * CTA size preset keys → admin-facing label. 'md' is the shipped size.
     * Same "classes live in the blade, not here" discipline as TITLE_SIZES —
     * see that constant's docblock for why.
     *
     * @var array<string, string>
     */
    public const CTA_SIZES = [
        'sm' => 'Small (compact)',
        'md' => 'Medium (default)',
        'lg' => 'Large',
    ];

    /** @return array{light: ?string, dark: ?string, description: string, enabled: bool, title: string, title_size: string, cta_size: string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $desc = trim((string) Setting::getValue(self::DESC_KEY, ''));
                $title = trim((string) Setting::getValue(self::TITLE_KEY, ''));
                $titleSize = (string) Setting::getValue(self::TITLE_SIZE_KEY, '');
                $ctaSize = (string) Setting::getValue(self::CTA_SIZE_KEY, '');

                return [
                    'light' => Setting::getValue(self::LIGHT_KEY) ?: null,
                    'dark' => Setting::getValue(self::DARK_KEY) ?: null,
                    'description' => $desc !== '' ? $desc : self::DEFAULT_DESCRIPTION,
                    // Default ON so existing installs are unchanged; the admin can
                    // turn the hero image off without removing the uploaded art.
                    'enabled' => (bool) Setting::getValue(self::ENABLED_KEY, true),
                    'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
                    'title_size' => array_key_exists($titleSize, self::TITLE_SIZES) ? $titleSize : self::DEFAULT_TITLE_SIZE,
                    'cta_size' => array_key_exists($ctaSize, self::CTA_SIZES) ? $ctaSize : self::DEFAULT_CTA_SIZE,
                ];
            } catch (\Throwable) {
                return [
                    'light' => null, 'dark' => null,
                    'description' => self::DEFAULT_DESCRIPTION, 'enabled' => true,
                    'title' => self::DEFAULT_TITLE, 'title_size' => self::DEFAULT_TITLE_SIZE,
                    'cta_size' => self::DEFAULT_CTA_SIZE,
                ];
            }
        });
    }

    public static function light(): ?string
    {
        return self::current()['light'];
    }

    public static function dark(): ?string
    {
        return self::current()['dark'];
    }

    /** The dashboard-home description line — never empty (falls back to default). */
    public static function description(): string
    {
        return self::current()['description'];
    }

    /** Admin toggle: is the dashboard hero image switched on? (Default true.) */
    public static function enabled(): bool
    {
        return self::current()['enabled'];
    }

    /** Whether at least one hero image is uploaded. */
    public static function isSet(): bool
    {
        $c = self::current();

        return filled($c['light']) || filled($c['dark']);
    }

    /** The dashboard hero headline — admin override or the default. Never empty. */
    public static function title(): string
    {
        return self::current()['title'];
    }

    /** The admin-chosen headline size preset (sm|md|lg|xl); always a valid key. */
    public static function titleSize(): string
    {
        return self::current()['title_size'];
    }

    /** The admin-chosen CTA button size preset (sm|md|lg); always a valid key. */
    public static function ctaSize(): string
    {
        return self::current()['cta_size'];
    }

    /**
     * The title split for the two-tone heading treatment: the first word renders
     * plain, the rest renders in the gradient accent — the same visual pattern
     * as the shipped "My" / "Connectivity" split, generalised to any admin-typed
     * title. A single-word title has no "rest" — the Blade partial then applies
     * the gradient to the whole word instead of leaving it plain.
     */
    public static function titleFirstWord(): string
    {
        return explode(' ', self::title(), 2)[0];
    }

    /** Every word after the first (see titleFirstWord()); '' for a single word. */
    public static function titleRestWords(): string
    {
        return explode(' ', self::title(), 2)[1] ?? '';
    }

    /** Whether the dashboard should actually SHOW the hero image right now:
     *  an image is uploaded AND the admin switch is on. */
    public static function showsOnDashboard(): bool
    {
        return self::isSet() && self::enabled();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isHeroKey(string $key): bool
    {
        return in_array($key, [
            self::LIGHT_KEY, self::DARK_KEY, self::DESC_KEY, self::ENABLED_KEY,
            self::TITLE_KEY, self::TITLE_SIZE_KEY, self::CTA_SIZE_KEY,
        ], true);
    }
}
