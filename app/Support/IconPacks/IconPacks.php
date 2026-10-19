<?php

namespace App\Support\IconPacks;

use App\Models\Setting;

/**
 * Icon pack registry and recolouring (works in the master and in the white-label forks).
 *
 * The menu icon art is a single set of duotone SVGs that colour themselves from two CSS variables (--nic-ic, --nic-ac). A pack is just those two
 * colours, so applying a pack never means shipping another copy of the files: the resolver points an icon at `icon-pack.svg`, which streams the one
 * canonical file with the pack's colours written in (long immutable cache, so each recoloured icon is built once per browser and once per CDN edge).
 *
 * Which pack is active: the admin's choice (Setting `icons.pack`), or "follow the theme" (the default), where the active theme preset's slug picks the
 * pack of the same name. A theme without a pack of its own falls back to the install's default pack.
 */
final class IconPacks
{
    public const SETTING = 'icons.pack';

    public const FOLLOW = 'follow-theme';

    /** @return array<string, array{label: string, ic: string, ac: string}> */
    public static function all(): array
    {
        return (array) config('icon_packs.packs', []);
    }

    public static function exists(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    public static function defaultSlug(): string
    {
        $d = (string) config('icon_packs.default', 'orchid-lime');

        return self::exists($d) ? $d : (array_key_first(self::all()) ?? 'orchid-lime');
    }

    /** The admin's stored choice: a pack slug or FOLLOW. */
    public static function choice(): string
    {
        try {
            $v = (string) Setting::getValue(self::SETTING, self::FOLLOW);
        } catch (\Throwable) {
            return self::FOLLOW;
        }

        return ($v === self::FOLLOW || self::exists($v)) ? $v : self::FOLLOW;
    }

    public static function setChoice(string $slug): void
    {
        abort_unless($slug === self::FOLLOW || self::exists($slug), 422);
        Setting::setValue(self::SETTING, $slug, 'ui');
    }

    /** The pack in force right now. */
    public static function activeSlug(): string
    {
        $choice = self::choice();
        if ($choice !== self::FOLLOW) {
            return $choice;
        }
        try {
            $theme = class_exists(\App\Support\ThemePreset::class) ? \App\Support\ThemePreset::slug() : '';
        } catch (\Throwable) {
            $theme = '';
        }

        return self::exists($theme) ? $theme : self::defaultSlug();
    }

    /**
     * The two colours for a pack in light or dark mode, nudged until the icon line reads against the surface it sits on (3:1, the WCAG minimum for
     * graphics), so a near-black pack never vanishes on a dark background and a pale one never washes out on white.
     *
     * @return array{ic: string, ac: string}
     */
    public static function colors(string $slug, string $mode = 'light'): array
    {
        $p = self::all()[$slug] ?? self::all()[self::defaultSlug()] ?? ['ic' => '#0F766E', 'ac' => '#F5A524'];
        $surface = $mode === 'dark' ? '#0B1624' : '#FFFFFF';

        return ['ic' => self::readable($p['ic'], $surface), 'ac' => self::readable($p['ac'], $surface)];
    }

    /** Rewrites the palette rule of one canonical icon with a pack's colours. */
    public static function recolor(string $svg, string $slug, string $mode = 'light'): string
    {
        $c = self::colors($slug, $mode);

        return (string) preg_replace('/(svg\.nic(?:-admin)?\{)--nic-ic:#[0-9A-Fa-f]{3,8};--nic-ac:#[0-9A-Fa-f]{3,8}(\})/', '$1--nic-ic:'.$c['ic'].';--nic-ac:'.$c['ac'].'$2', $svg, 1);
    }

    /** URL of one icon in a pack. `$file` is the canonical file name without extension (eg app-more-numbers or app-more-numbers-dark). */
    public static function url(string $group, string $file, ?string $slug = null): string
    {
        return route('icon-pack.svg', ['pack' => $slug ?? self::activeSlug(), 'group' => $group, 'file' => $file, 'v' => self::version()]);
    }

    /** Changes when the palette data changes, so a corrected colour is never served from a stale cache. */
    public static function version(): string
    {
        return substr(md5(json_encode(self::all())), 0, 8);
    }

    private static function readable(string $hex, string $surface): string
    {
        $fg = self::rgb($hex);
        $bg = self::rgb($surface);
        if (self::contrast($fg, $bg) >= 3.0) {
            return strtoupper($hex);
        }
        // Move toward white on a dark surface and toward black on a light one, a little at a time, keeping the hue.
        $target = self::luminance($bg) < 0.5 ? [255, 255, 255] : [0, 0, 0];
        for ($t = 0.1; $t <= 1.0; $t += 0.05) {
            $mix = [(int) round($fg[0] + ($target[0] - $fg[0]) * $t), (int) round($fg[1] + ($target[1] - $fg[1]) * $t), (int) round($fg[2] + ($target[2] - $fg[2]) * $t)];
            if (self::contrast($mix, $bg) >= 3.0) {
                return sprintf('#%02X%02X%02X', ...$mix);
            }
        }

        return strtoupper($hex);
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private static function luminance(array $c): float
    {
        $f = fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
    }

    private static function contrast(array $a, array $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }
}
