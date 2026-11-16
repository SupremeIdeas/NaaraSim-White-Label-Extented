<?php

namespace App\Support;

/**
 * Which Host headers an install answers in production. It always follows the install's OWN domain (APP_URL), so a white-label installed on its own
 * domain is never locked out, and it stays OFF whenever it cannot be sure: no APP_URL yet, a placeholder such as localhost, or TRUSTED_HOSTS=*.
 * TRUSTED_HOSTS adds more (comma-separated), e.g. a second domain, a staging host or a health-check name.
 */
class TrustedHosts
{
    /** @return list<string> regex patterns for Laravel's trustHosts(); empty = trust every host */
    public static function patterns(?string $appUrl = null, ?string $env = null, ?string $extra = null): array
    {
        $appUrl ??= (string) env('APP_URL', '');
        $env ??= (string) env('APP_ENV', 'production');
        $extra ??= (string) env('TRUSTED_HOSTS', '');

        if (! in_array($env, ['production', 'staging'], true)) {
            return [];
        }
        $list = array_values(array_filter(array_map('trim', explode(',', $extra))));
        if (in_array('*', $list, true)) {
            return [];
        }
        $host = parse_url($appUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '' || self::isPlaceholder($host)) {
            return [];   // not configured yet: never lock a fresh install out
        }
        $host = strtolower($host);
        $bare = preg_replace('/^www\./', '', $host);
        $hosts = array_merge([$bare, 'www.'.$bare], array_map('strtolower', $list));

        return array_values(array_unique(array_map(fn ($h) => '^'.preg_quote($h, '/').'$', $hosts)));
    }

    public static function isPlaceholder(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'], true)
            || (bool) preg_match('/\.(test|local|localhost|example|invalid)$/', $host)
            || in_array($host, ['example.com', 'example.org', 'example.net'], true);
    }
}
