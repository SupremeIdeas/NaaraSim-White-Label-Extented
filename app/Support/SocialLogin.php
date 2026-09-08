<?php

namespace App\Support;

/**
 * Social-login availability + config helpers (Module 23). Google sign-in only
 * appears once the admin has saved both credentials (mirrors ProviderStatus:
 * a feature is off until it's really configured).
 */
class SocialLogin
{
    public static function googleEnabled(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }

    /**
     * Absolute redirect URI for the Google callback. If the admin left the
     * configured value relative (the default), anchor it to the current site.
     */
    public static function googleRedirect(): string
    {
        $configured = (string) config('services.google.redirect');

        if ($configured !== '' && str_starts_with($configured, 'http')) {
            return $configured;
        }

        return url($configured !== '' ? $configured : '/auth/google/callback');
    }
}
