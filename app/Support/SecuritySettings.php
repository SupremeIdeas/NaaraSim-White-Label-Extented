<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-toggleable runtime security controls (blueprint Section 30). The two
 * headers most likely to clash with an unknown self-hosted environment — the
 * Content-Security-Policy and HSTS (force-HTTPS) — can be switched on/off from
 * the admin panel with no redeploy. Everything defaults to ON (secure); the
 * admin only turns one off if it visibly breaks something on their host.
 *
 * Values are cached and busted on any `security.*` setting save. Reads degrade
 * to the config defaults if settings/DB are unavailable (e.g. pre-install), so
 * a hardened default is always applied.
 */
class SecuritySettings
{
    // Versioned so adding keys to the cached shape invalidates any stale cache
    // left over from a previous deploy (a missing key must never crash a read).
    private const CACHE_KEY = 'security.settings.v4';

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'csp_enabled' => self::boolSetting('security.csp_enabled', (bool) config('security.csp.enabled', true)),
                    'hsts_enabled' => self::boolSetting('security.hsts_enabled', (bool) config('security.hsts.enabled', true)),
                    // 2FA for the admin panel is OPT-IN: password+email only by
                    // default, the super-admin turns it on for an extra layer.
                    'admin_2fa_required' => self::boolSetting('security.admin_2fa_required', (bool) config('admin.require_2fa', false)),
                    // Cloudflare Turnstile bot challenge — OFF by default; the
                    // admin turns it on once the site/secret keys are set.
                    'turnstile_enabled' => self::boolSetting('security.turnstile_enabled', false),
                    // Panel-managed admin access controls (owner request) — both
                    // OFF by default so the admin can sign in from any device or
                    // country until they deliberately restrict it.
                    'admin_country_allowlist_enabled' => self::boolSetting('security.admin_country_allowlist_enabled', false),
                    'admin_allowed_countries' => self::arraySetting('security.admin_allowed_countries'),
                    'admin_ip_allowlist_enabled' => self::boolSetting('security.admin_ip_allowlist_enabled', false),
                    'admin_ip_allowlist' => self::arraySetting('security.admin_ip_allowlist'),
                ];
            } catch (\Throwable) {
                return [
                    'csp_enabled' => (bool) config('security.csp.enabled', true),
                    'hsts_enabled' => (bool) config('security.hsts.enabled', true),
                    'admin_2fa_required' => (bool) config('admin.require_2fa', false),
                    'turnstile_enabled' => false,
                    'admin_country_allowlist_enabled' => false,
                    'admin_allowed_countries' => [],
                    'admin_ip_allowlist_enabled' => false,
                    'admin_ip_allowlist' => [],
                ];
            }
        });
    }

    public static function adminCountryAllowlistEnabled(): bool
    {
        return (bool) (self::current()['admin_country_allowlist_enabled'] ?? false);
    }

    /** @return list<string> ISO-3166 alpha-2 codes (uppercase). */
    public static function adminAllowedCountries(): array
    {
        return self::current()['admin_allowed_countries'] ?? [];
    }

    public static function adminIpAllowlistEnabled(): bool
    {
        return (bool) (self::current()['admin_ip_allowlist_enabled'] ?? false);
    }

    /** @return list<string> */
    public static function adminIpAllowlist(): array
    {
        return self::current()['admin_ip_allowlist'] ?? [];
    }

    /** @return list<string> */
    private static function arraySetting(string $key): array
    {
        $value = Setting::getValue($key);

        return is_array($value) ? array_values($value) : [];
    }

    public static function cspEnabled(): bool
    {
        return self::current()['csp_enabled'] ?? (bool) config('security.csp.enabled', true);
    }

    public static function hstsEnabled(): bool
    {
        return self::current()['hsts_enabled'] ?? (bool) config('security.hsts.enabled', true);
    }

    public static function admin2faRequired(): bool
    {
        return self::current()['admin_2fa_required'] ?? (bool) config('admin.require_2fa', false);
    }

    public static function turnstileEnabled(): bool
    {
        return self::current()['turnstile_enabled'] ?? false;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isSecurityKey(string $key): bool
    {
        return str_starts_with($key, 'security.');
    }

    private static function boolSetting(string $key, bool $default): bool
    {
        $value = Setting::getValue($key);

        return $value === null ? $default : (bool) $value;
    }
}
