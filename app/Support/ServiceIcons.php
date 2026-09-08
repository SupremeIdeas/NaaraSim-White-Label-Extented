<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Service-logo resolution for numbers/OTP services (Module 27.5, owner
 * request): every service a user can buy a number for (WhatsApp, Google,
 * Facebook, OkCupid, POF, …) shows a recognisable logo, with this priority:
 *
 *   1. Admin-uploaded override  (Admin → Service icons — wins, and fills gaps
 *      when a live provider API doesn't return artwork)
 *   2. Provider API icon URL    (passed in when the live APIs supply one)
 *   3. Bundled brand-mark sprite (partials/service-icon-sprite.blade.php)
 *   4. Letter avatar fallback   (never a broken image)
 *
 * Overrides are one cached Setting row (service_icons.overrides: slug => url).
 */
class ServiceIcons
{
    public const SETTING_KEY = 'service_icons.overrides';

    private const CACHE_KEY = 'service_icons.resolved';

    /** Slugs that have a bundled sprite symbol (svc-{slug}) — real brand logos. */
    public const BUNDLED = [
        'whatsapp', 'telegram', 'facebook', 'google', 'instagram', 'tiktok', 'x', 'snapchat',
        'discord', 'tinder', 'okcupid', 'uber', 'apple', 'netflix', 'paypal', 'viber',
        'signal', 'wechat', 'binance', 'coinbase', 'revolut', 'cashapp', 'venmo', 'twitch',
        'spotify', 'airbnb', 'aliexpress', 'ebay', 'steam', 'epicgames', 'protonmail', 'line',
        'grab', 'doordash', 'deliveroo', 'wise', 'payoneer', 'zoom', 'reddit', 'pinterest',
        'shopee', 'tumblr', 'vk', 'naver', 'kakaotalk', 'badoo', 'microsoft',
    ];

    /** Common provider aliases → our slugs (5sim/SMS-Activate naming). */
    private const ALIASES = [
        'twitter' => 'x', 'fb' => 'facebook', 'wa' => 'whatsapp',
        'gmail' => 'google', 'youtube' => 'google', 'plentyoffish' => 'pof',
    ];

    /**
     * Resolve how to render a service's icon.
     *
     * @return array{type: 'img'|'sprite'|'letter', url?: string, id?: string, letter?: string}
     */
    public static function resolve(string $service, ?string $apiUrl = null): array
    {
        $slug = self::slug($service);

        if ($url = self::overrides()[$slug] ?? null) {
            return ['type' => 'img', 'url' => $url];
        }

        if ($apiUrl) {
            return ['type' => 'img', 'url' => $apiUrl];
        }

        if (in_array($slug, self::BUNDLED, true)) {
            return ['type' => 'sprite', 'id' => 'svc-'.$slug];
        }

        return ['type' => 'letter', 'letter' => mb_strtoupper(mb_substr($slug, 0, 1) ?: '?')];
    }

    public static function slug(string $service): string
    {
        $slug = strtolower(trim($service));

        return self::ALIASES[$slug] ?? $slug;
    }

    /**
     * Whether a service resolves to a real logo (admin override or bundled brand
     * mark) rather than the letter-avatar fallback. Used to sort services WITH
     * icons ahead of icon-less ones in the picker (owner request).
     */
    public static function hasIcon(string $service, ?string $apiUrl = null): bool
    {
        return self::resolve($service, $apiUrl)['type'] !== 'letter';
    }

    /** @return array<string, string> slug => image url */
    public static function overrides(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $stored = Setting::getValue(self::SETTING_KEY, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function saveOverride(string $service, string $url): void
    {
        $map = self::overrides();
        $map[self::slug($service)] = $url;
        Setting::setValue(self::SETTING_KEY, $map, 'icons', 'Admin service-logo overrides.');
        self::flush();
    }

    public static function removeOverride(string $service): void
    {
        $map = self::overrides();
        unset($map[self::slug($service)]);
        Setting::setValue(self::SETTING_KEY, $map, 'icons');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isServiceIconKey(string $key): bool
    {
        return $key === self::SETTING_KEY;
    }
}
