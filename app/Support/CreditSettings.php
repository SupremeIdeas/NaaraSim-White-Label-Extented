<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * NaaraCredits economy config (loyalty module). Admin-tunable so the operator
 * controls the whole reward economy and keeps it profitable: the rate
 * (credits per USD), the earn amounts per task, and the checkout redemption cap.
 *
 * Profitability by design: rewarded-ad credits should be set well below what the
 * ad network pays for a view, and the redemption cap ensures credits can only
 * pay part of a purchase — the platform always collects real money on top.
 */
class CreditSettings
{
    private const CACHE_KEY = 'credits.settings.v1';

    public static function isCreditKey(string $key): bool
    {
        return str_starts_with($key, 'credits.');
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'enabled' => (bool) Setting::getValue('credits.enabled', true),
                    'per_usd' => max(1, (int) Setting::getValue('credits.per_usd', 100)),
                    'signup_bonus' => max(0, (int) Setting::getValue('credits.signup_bonus', 0)),
                    'checkin_daily' => max(0, (int) Setting::getValue('credits.checkin_daily', 5)),
                    'checkin_cooldown_hours' => max(1, (int) Setting::getValue('credits.checkin_cooldown_hours', 24)),
                    'first_purchase_bonus' => max(0, (int) Setting::getValue('credits.first_purchase_bonus', 50)),
                    'max_redeem_pct' => min(100, max(0, (int) Setting::getValue('credits.max_redeem_pct', 50))),
                    // Rewarded ads (offerwall) — postback-verified.
                    'ads_enabled' => (bool) Setting::getValue('credits.ads_enabled', false),
                    'ad_provider' => (string) Setting::getValue('credits.ad_provider', ''),
                    'ad_offerwall_url' => (string) Setting::getValue('credits.ad_offerwall_url', ''),
                    'ad_daily_cap' => max(0, (int) Setting::getValue('credits.ad_daily_cap', 20)),
                ];
            } catch (\Throwable) {
                return self::defaults();
            }
        });
    }

    private static function defaults(): array
    {
        return [
            'enabled' => true, 'per_usd' => 100, 'signup_bonus' => 0, 'checkin_daily' => 5,
            'checkin_cooldown_hours' => 24, 'first_purchase_bonus' => 50, 'max_redeem_pct' => 50,
            'ads_enabled' => false, 'ad_provider' => '', 'ad_offerwall_url' => '', 'ad_daily_cap' => 20,
        ];
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    public static function perUsd(): int
    {
        return (int) self::all()['per_usd'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Rewarded ads live: economy on, ads on, provider + postback secret set. */
    public static function adsActive(): bool
    {
        return self::enabled()
            && (bool) self::all()['ads_enabled']
            && filled(self::all()['ad_offerwall_url'])
            && filled(config('services.offerwall.postback_secret'));
    }

    public static function creditsToUsd(float $credits): float
    {
        return round($credits / self::perUsd(), 4);
    }

    public static function usdToCredits(float $usd): float
    {
        return round($usd * self::perUsd(), 2);
    }
}
