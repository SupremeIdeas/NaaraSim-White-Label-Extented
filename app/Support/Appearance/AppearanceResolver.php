<?php

namespace App\Support\Appearance;

use App\Models\AppearancePreset;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAppearance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * What does THIS member's dashboard look like? (Prompt 20 §17.3). The only place that answers the question.
 *
 *  order: appearance locked by the operator (admins exempt) -> the member's own saved choice, if that preset still exists
 *         AND is enabled -> the platform default. A disabled/removed preset falls back silently WITHOUT deleting the
 *         member's row, so re-enabling it restores their choice.
 *
 * Guests and anything outside the dashboard (marketing, auth, emails, PDFs) always get the platform defaults. Nothing
 * here ever touches providers, wallets, orders or billing.
 */
class AppearanceResolver
{
    public const LOCK = 'appearance.lock_users';

    public const ALLOW_CUSTOM = 'appearance.allow_custom_accent';

    private const PLATFORM_KEY = 'appearance:platform';

    /** The cache key carries the licensed-skins version, so a selection or allowance change invalidates every resolved appearance at once. */
    private static function platformKey(): string
    {
        return self::PLATFORM_KEY.':v'.(LicensedSkins::enforced() ? LicensedSkins::version() : 0);
    }

    /**
     * @return array{skin: string, accent: string, accent_hex: ?string, mode: ?string, dials: array<string, string>, locked: bool, user: bool}
     */
    public static function for(?User $user): array
    {
        $r = self::core($user);
        // Skin-specific data contracts (Prompt 20 §44, §45): computed only for the skin that needs them, never fatal.
        $r['country'] = $r['skin'] === 'passport' ? ActiveCountryResolver::for($user) : null;
        $r['tod'] = $r['skin'] === 'golden' ? self::timeOfDay($user) : null;

        return $r;
    }

    /** dawn 05:00-07:59, day 08:00-16:59, dusk 17:00-19:59, night 20:00-04:59, in the member's own timezone (§45); `day` when unknown. */
    public static function timeOfDay(?User $user, ?\DateTimeInterface $now = null): string
    {
        try {
            $tz = $user && filled($user->timezone) ? new \DateTimeZone((string) $user->timezone) : null;
        } catch (\Throwable) {
            $tz = null;
        }
        if ($tz === null) {
            return 'day';
        }
        $h = (int) ($now ? \Carbon\Carbon::instance($now) : now())->setTimezone($tz)->format('G');

        return match (true) {
            $h >= 5 && $h < 8 => 'dawn',
            $h >= 8 && $h < 17 => 'day',
            $h >= 17 && $h < 20 => 'dusk',
            default => 'night',
        };
    }

    private static function core(?User $user): array
    {
        $platform = self::platform();
        $dials = self::dialDefaults();
        $base = ['skin' => $platform['skin'], 'accent' => $platform['accent'], 'accent_hex' => null, 'mode' => null, 'dials' => $dials, 'locked' => false, 'user' => false];

        if ($user === null) {
            return $base;
        }
        $base['user'] = true;
        $locked = $platform['locked'] && ! self::isAdmin($user);
        $base['locked'] = $locked;
        if ($locked) {
            return $base;   // the lock forces defaults for every dial too; the member's row is kept untouched
        }

        $row = self::row($user->id);
        if ($row === null) {
            return $base;
        }

        if ($row['skin_key'] !== null && in_array($row['skin_key'], $platform['skins'], true)) {
            $base['skin'] = $row['skin_key'];
        }
        if ($row['accent_key'] === 'custom') {
            if ($platform['allow_custom'] && AccentDeriver::isValidHex($row['accent_hex'])) {
                $base['accent'] = 'custom';
                $base['accent_hex'] = $row['accent_hex'];
            }
        } elseif ($row['accent_key'] !== null && in_array($row['accent_key'], $platform['accents'], true)) {
            $base['accent'] = $row['accent_key'];
        }
        $base['mode'] = in_array($row['mode'], (array) config('appearance.modes'), true) ? $row['mode'] : null;
        foreach ($dials as $dial => $default) {
            $v = $row[$dial] ?? null;
            if ($v !== null && array_key_exists($v, config("appearance.dials.{$dial}.options"))) {
                $base['dials'][$dial] = $v;
            }
        }

        return $base;
    }

    /** The platform-wide picture: defaults, which presets are enabled, lock, custom-accent switch. */
    public static function platform(): array
    {
        return Cache::remember(self::platformKey(), 600, function () {
            $cfg = config('appearance');
            $out = [
                'skin' => $cfg['default_skin'], 'accent' => $cfg['default_accent'],
                'skins' => (array) $cfg['built'], 'accents' => array_keys($cfg['accents']),
                'locked' => false, 'allow_custom' => true,
            ];
            try {
                $out['locked'] = (bool) Setting::getValue(self::LOCK, false);
                $out['allow_custom'] = (bool) Setting::getValue(self::ALLOW_CUSTOM, true);
                if (Schema::hasTable('appearance_presets') && AppearancePreset::query()->exists()) {
                    $rows = AppearancePreset::query()->where('enabled', true)->get();
                    // A skin can only be offered once its stylesheet exists, whatever the row says.
                    $out['skins'] = $rows->where('kind', AppearancePreset::SKIN)->pluck('key')->intersect($cfg['built'])->values()->all();
                    $out['accents'] = $rows->where('kind', AppearancePreset::ACCENT)->pluck('key')->values()->all();
                    $default = fn (string $kind, string $fallback) => (string) (AppearancePreset::query()->where('kind', $kind)->where('is_default', true)->where('enabled', true)->value('key') ?? $fallback);
                    $out['skin'] = $default(AppearancePreset::SKIN, $out['skin']);
                    $out['accent'] = $default(AppearancePreset::ACCENT, $out['accent']);
                }
            } catch (\Throwable) {
                // Tables not migrated yet, or no DB at install time: the shipped defaults stand.
            }
            // White-label licence (Prompt 22): only the skins the licence unlocks AND the licensee chose may be resolved, picked or previewed.
            // The intersection is defence in depth: hand-editing appearance_presets can never unlock a skin.
            if (LicensedSkins::enforced()) {
                $out['skins'] = array_values(array_intersect(LicensedSkins::available(), $out['skins'] ?: LicensedSkins::available()));
                $out['skin'] = LicensedSkins::default();
            }
            if (! in_array($out['skin'], $out['skins'], true)) {
                $out['skin'] = LicensedSkins::enforced() ? LicensedSkins::default() : $cfg['default_skin'];
                $out['skins'][] = $out['skin'];
            }

            return $out;
        });
    }

    /** @return array<string, string> dial => default value (the first option) */
    public static function dialDefaults(): array
    {
        return collect(config('appearance.dials'))->map(fn ($d) => (string) array_key_first($d['options']))->all();
    }

    public static function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /** Drop every cached view of one member (after they save, or an admin changes their access). */
    public static function forget(int $userId): void
    {
        Cache::forget("appearance:user:{$userId}");
    }

    /** Drop the platform picture (after an admin changes defaults, enable flags, lock or the custom-accent switch). */
    public static function forgetPlatform(): void
    {
        Cache::forget(self::platformKey());
    }

    /** @return array<string, ?string>|null */
    private static function row(int $userId): ?array
    {
        // Per-user cache key (isolation rule 1): never a global key. Falsy marker caches "no row" too.
        $row = Cache::remember("appearance:user:{$userId}", 600, function () use ($userId) {
            try {
                $r = UserAppearance::query()->where('user_id', $userId)->first();
            } catch (\Throwable) {
                return false;
            }

            return $r ? $r->only(['skin_key', 'accent_key', 'accent_hex', 'mode', ...UserAppearance::DIALS]) : false;
        });

        return $row === false ? null : $row;
    }

    // ---- rendering helpers (used by the layout) -----------------------------------------------------------------

    /** The data-nx-* attributes for <html>. Server-rendered on every response so a wire:navigate morph never drops them. */
    public static function htmlAttributes(array $r): string
    {
        $attrs = ['data-nx-skin' => $r['skin'], 'data-nx-accent' => $r['accent'], 'data-nx-user' => $r['user'] ? '1' : '0'];
        foreach ($r['dials'] as $dial => $value) {
            $attrs["data-nx-{$dial}"] = $value;
        }
        if ($r['mode'] !== null) {
            $attrs['data-nx-mode'] = $r['mode'];
        }
        if (! empty($r['country'])) {
            $attrs['data-nx-country'] = $r['country'];
        }
        if (! empty($r['tod'])) {
            $attrs['data-nx-tod'] = $r['tod'];
        }

        return collect($attrs)->map(fn ($v, $k) => $k.'="'.e($v).'"')->implode(' ');
    }

    /**
     * CSS for the member's custom accent. Both mode variants are emitted and the `.dark` class (set by the existing
     * pre-paint script, including for mode=system) picks one, so the server never has to know the final mode.
     */
    public static function customAccentCss(array $r): string
    {
        // L0 -> L2 bridge (Prompt 20 §13): on a non-default platform theme, the default accent ("Naara Teal") means "inherit the
        // theme's brand colour", so the dashboard follows the operator's theme until the member picks an accent of their own.
        if ($r['accent'] === 'teal' && ($bridge = self::themeBridgeHex()) !== null) {
            $tone = fn (string $mode) => self::accentBlock(AccentDeriver::derive($bridge, $mode), false);

            return 'html:not(.dark)[data-nx-accent=teal]{'.$tone('light').'}html.dark[data-nx-accent=teal]{'.$tone('dark').'}';
        }
        if ($r['accent'] !== 'custom' || ! AccentDeriver::isValidHex($r['accent_hex'])) {
            return '';
        }
        $block = fn (string $mode) => self::accentBlock(AccentDeriver::derive($r['accent_hex'], $mode), true);

        return 'html[data-nx-accent=custom]{'.$block('light').'}html.dark[data-nx-accent=custom]{'.$block('dark').'}';
    }

    /** The active platform theme's primary colour as #rrggbb, or null on the default theme / when it has none (the shipped teal stands). */
    public static function themeBridgeHex(): ?string
    {
        try {
            if (\App\Support\ThemePreset::slug() === \App\Support\ThemePreset::DEFAULT_SLUG) {
                return null;
            }
            $triple = \App\Support\ThemePreset::tokens()['colors']['primary'] ?? null;
            if (! is_string($triple) || ! \App\Support\ThemePreset::isValidColorTriple($triple)) {
                return null;
            }
            $hex = \App\Support\ThemePreset::channelTripleToHex($triple);

            return AccentDeriver::isValidHex($hex) ? $hex : null;
        } catch (\Throwable) {
            return null;   // never block a page render on theming
        }
    }

    /** CSS custom properties for one derived accent. `$brand` also maps the legacy brand variables (never gold, which is money). */
    private static function accentBlock(array $t, bool $brand): string
    {
        $css = "--nx-teal:{$t['teal']};--nx-cta-a:{$t['cta_a']};--nx-cta-b:{$t['cta_b']};--nx-teal-ink:{$t['teal_ink']};"
            ."--nx-acc-a:{$t['acc_a']};--nx-acc-b:{$t['acc_b']};--nx-acc-c:{$t['acc_c']};";

        return $brand ? $css."--brand-primary:{$t['cta_b']};--brand-primary-dark:{$t['acc_c']};" : $css;
    }
}
