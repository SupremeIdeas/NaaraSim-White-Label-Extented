<?php

namespace App\Support\Activation;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Everything this install knows about its own licence, kept in the database so the owner can activate from the admin screen instead of editing
 * .env. The API token is stored encrypted; the licence key itself is never stored (only its last four characters, to recognise it on screen).
 * A token set in .env (NAARA_UPDATE_API_TOKEN) still works and is used when nothing has been activated in-app.
 */
final class LicenseState
{
    public const GROUP = 'white_label';

    public const STATUSES = ['none', 'active', 'suspended', 'revoked', 'unreachable', 'unverified'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue('activation.'.$key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        Setting::setValue('activation.'.$key, $value, self::GROUP);
    }

    public static function platformUrl(): string
    {
        $saved = trim((string) self::get('platform_url', ''));

        return rtrim($saved !== '' ? $saved : (string) config('updater.original_platform_base_url'), '/');
    }

    public static function token(): string
    {
        $stored = (string) self::get('token', '');
        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                // An APP_KEY change makes the old value unreadable: fall through to .env, and the screen asks to activate again.
            }
        }

        return (string) config('updater.api_token');
    }

    public static function isActivated(): bool
    {
        return self::token() !== '' && self::platformUrl() !== '';
    }

    /** none | active | suspended | revoked | unreachable */
    public static function status(): string
    {
        if (! self::isActivated()) {
            return 'none';
        }
        $s = (string) self::get('status', 'active');

        return in_array($s, self::STATUSES, true) ? $s : 'active';
    }

    /** The public address this install runs on, as the original platform should see it: host only, no scheme or path. */
    public static function domain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            try {
                $req = request()->getHost();
                $host = $req !== '' ? $req : $host;
            } catch (\Throwable) {
            }
        }

        return strtolower((string) $host);
    }

    public static function storeActivation(string $platformUrl, string $token, string $keyLastFour, array $instance): void
    {
        self::put('platform_url', rtrim($platformUrl, '/'));
        self::put('token', Crypt::encryptString($token));
        self::put('key_last4', $keyLastFour);
        self::put('reference', (string) ($instance['slug'] ?? ''));
        self::put('tier', (string) ($instance['tier'] ?? ''));
        self::put('registered_brand', (string) ($instance['brand_name'] ?? ''));
        self::put('domain', self::domain());
        self::put('activated_at', now()->toIso8601String());
        self::put('status', 'active');
        self::put('last_error', '');
    }

    /** Called after every contact with the original platform, from the admin button, the scheduler or activation itself. */
    public static function recordCheckIn(bool $ok, int $http = 0, ?string $error = null, ?string $level = null): void
    {
        self::put('last_attempt_at', now()->toIso8601String());
        if ($ok) {
            self::put('status', 'active');
            self::put('last_checkin_at', now()->toIso8601String());
            self::put('last_error', '');
            if ($level !== null && $level !== '') {
                self::put('tier', $level);
            }

            return;
        }
        self::put('last_error', (string) $error);
        if ($http === -1) {
            self::put('status', 'unverified');   // the platform answered, but not with a signature this install can trust
        } elseif ($http === 401 || $http === 403) {
            self::put('status', $http === 401 ? 'revoked' : 'suspended');
            // The platform itself said no (revoked, suspended, token killed): what it signed earlier stops counting at once, not at its expiry.
            self::put('entitlement_signed', '');
            \App\Support\FeatureEntitlements::bust();
        } elseif ($http === 0) {
            self::put('status', 'unreachable');
        }
        // Any other failure (5xx, 404 while the distribution API is off) leaves the last known status alone: a licence is never taken away by a blip.
    }

    /** The signed entitlement last accepted from the platform, re-verified on every read so editing the database cannot change what is unlocked. */
    public static function verifiedEntitlement(): ?array
    {
        $raw = (string) self::get('entitlement_signed', '');
        $signed = $raw !== '' ? json_decode($raw, true) : null;
        if (! is_array($signed)) {
            return null;
        }

        return EntitlementVerifier::verify($signed, (string) self::get('reference', ''));
    }

    public static function clear(): void
    {
        foreach (['entitlement_signed', 'verified_at', 'token', 'key_last4', 'reference', 'tier', 'registered_brand', 'domain', 'activated_at', 'status', 'last_error', 'last_checkin_at', 'last_attempt_at'] as $k) {
            self::put($k, '');
        }
    }

    /** A platform address is accepted when it is https (http only on a local/dev install) and not an internal address. */
    public static function validPlatformUrl(string $url): bool
    {
        $p = parse_url(trim($url));
        if (! is_array($p) || empty($p['host']) || empty($p['scheme'])) {
            return false;
        }
        $local = app()->environment(['local', 'testing']);
        if (! in_array($p['scheme'], $local ? ['http', 'https'] : ['https'], true)) {
            return false;
        }
        if (! $local && (filter_var($p['host'], FILTER_VALIDATE_IP) !== false)
            && filter_var($p['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return ! isset($p['user']) && ! isset($p['pass']);
    }
}
