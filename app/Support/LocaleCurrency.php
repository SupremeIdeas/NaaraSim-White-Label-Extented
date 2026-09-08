<?php

namespace App\Support;

use App\Models\User;
use App\Services\Pricing\CurrencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves the currency a user SEES prices in (owner request — localized
 * pricing). USD stays the default/settlement currency everywhere; this only
 * chooses the display layer, in priority order:
 *
 *   1. an explicit choice this session (the currency switcher),
 *   2. the user's saved display_currency,
 *   3. their profile country_code → local currency,
 *   4. a best-effort IP-geolocation lookup (free, cached, guests only),
 *   5. USD.
 *
 * Nothing here moves money — it's purely what the price is rendered in.
 */
class LocaleCurrency
{
    public const SESSION_KEY = 'display_currency';

    /** ISO-3166 alpha-2 country → the currency we display for it. */
    private const COUNTRY_CURRENCY = [
        'NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR',
        'GB' => 'GBP', 'IE' => 'EUR', 'CA' => 'CAD', 'IN' => 'INR',
        'US' => 'USD',
        // Eurozone
        'DE' => 'EUR', 'FR' => 'EUR', 'ES' => 'EUR', 'IT' => 'EUR', 'NL' => 'EUR',
        'PT' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR',
    ];

    /** The supported display currencies (with symbol + name) for a switcher. */
    public static function options(): array
    {
        return CurrencyService::SUPPORTED;
    }

    /** Map a country code to a supported display currency (USD default). */
    public static function forCountry(?string $iso): string
    {
        $iso = strtoupper((string) $iso);

        return self::COUNTRY_CURRENCY[$iso] ?? 'USD';
    }

    /** Persist a user's explicit choice (session + profile). Validated. */
    public static function choose(?User $user, string $currency): string
    {
        $currency = strtoupper($currency);
        if (! isset(CurrencyService::SUPPORTED[$currency])) {
            $currency = 'USD';
        }
        session([self::SESSION_KEY => $currency]);
        if ($user !== null) {
            $user->forceFill(['display_currency' => $currency])->save();
        }

        return $currency;
    }

    /** The currency to render prices in for this user (see class doc). */
    public static function resolve(?User $user = null): string
    {
        $session = session(self::SESSION_KEY);
        if (is_string($session) && isset(CurrencyService::SUPPORTED[$session])) {
            return $session;
        }

        if ($user !== null) {
            if ($user->display_currency && isset(CurrencyService::SUPPORTED[$user->display_currency])) {
                return $user->display_currency;
            }
            if ($user->country_code) {
                return self::forCountry($user->country_code);
            }
        }

        return self::detectFromIp() ?? 'USD';
    }

    /**
     * Best-effort IP geolocation (free, no key: ip-api.com), cached a day per IP.
     * Never throws and never blocks rendering — a miss just falls back to USD.
     */
    public static function detectFromIp(?string $ip = null): ?string
    {
        $ip = $ip ?: request()->ip();
        if (! $ip || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        return Cache::remember("geo.currency.$ip", 86400, function () use ($ip) {
            try {
                $iso = Http::timeout(4)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'countryCode,status',
                ])->json('countryCode');

                return $iso ? self::forCountry($iso) : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
