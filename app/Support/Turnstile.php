<?php

namespace App\Support;

/**
 * Cloudflare Turnstile (blueprint Section 33) — a privacy-friendly "checkmark"
 * bot challenge on login, register and support. The admin pastes the site +
 * secret keys (API-keys page) and flips a plain on/off toggle (Security page).
 *
 * Fail-open only when DISABLED: the widget renders and the token is verified
 * server-side only when Turnstile is both enabled AND fully configured. If the
 * admin never sets it up, auth works exactly as before — nothing to break.
 */
class Turnstile
{
    /** The Cloudflare origin the widget script + challenge iframe load from. */
    public const ORIGIN = 'https://challenges.cloudflare.com';

    /** Admin toggle (Security page). Off by default. */
    public static function enabled(): bool
    {
        return SecuritySettings::turnstileEnabled();
    }

    public static function configured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    /** Live: enabled by the admin AND both keys present. */
    public static function active(): bool
    {
        return self::enabled() && self::configured();
    }

    public static function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }
}
