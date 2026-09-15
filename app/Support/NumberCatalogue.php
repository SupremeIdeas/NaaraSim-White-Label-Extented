<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The number/rental catalogue — the COMPLETE set of countries and services a
 * user can pick from, so the storefront is never limited to a hand-curated
 * handful (blueprint Section 12; replaces the old inline lists in GetNumber).
 *
 * Two layers, merged and cached:
 *   1. A broad STATIC base (below) — ~120 countries and ~110 popular services —
 *      so it works out of the box with no provider keys.
 *   2. A SYNCED overlay pulled from the live provider APIs (5sim + HeroSMS)
 *      by `numbers:catalogue-sync`, so in production it becomes exhaustive —
 *      every country and every service the providers actually support — and
 *      stays fresh. Stored in Settings (numbers.catalogue.*), unioned over the
 *      base so a provider hiccup never shrinks the list.
 *
 * Country keys are 5sim-style slugs (usa, england, nigeria…) — the value the
 * buy flow and CountryFlags already use. Service keys are provider product
 * slugs (whatsapp, telegram…) mapped to a friendly label.
 */
class NumberCatalogue
{
    public const COUNTRIES_KEY = 'numbers.catalogue.countries';

    public const SERVICES_KEY = 'numbers.catalogue.services';

    /** Per-provider slug => provider country id, live-discovered (owner audit, 2026-09-15). */
    private const PROVIDER_MAP_KEY_PREFIX = 'numbers.catalogue.provider_map.';

    /** Per-provider slug => provider SERVICE id, live-discovered (owner audit, 2026-09-15). */
    private const PROVIDER_SERVICE_MAP_KEY_PREFIX = 'numbers.catalogue.provider_service_map.';

    private const CACHE_COUNTRIES = 'numbers.catalogue.countries.resolved';

    private const CACHE_SERVICES = 'numbers.catalogue.services.resolved';

    private const CACHE_PROVIDER_MAP_PREFIX = 'numbers.catalogue.provider_map.resolved.';

    private const CACHE_PROVIDER_SERVICE_MAP_PREFIX = 'numbers.catalogue.provider_service_map.resolved.';

    /**
     * Every country, slug => label. Merges the static base with the synced
     * overlay (synced wins on label), sorted by label. Degrades to the base if
     * the store is unreachable.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_COUNTRIES, function () {
                $synced = Setting::getValue(self::COUNTRIES_KEY, []);
                $merged = array_merge(self::baseCountries(), is_array($synced) ? $synced : []);
                asort($merged, SORT_NATURAL | SORT_FLAG_CASE);

                return $merged;
            });
        } catch (\Throwable) {
            return self::baseCountries();
        }
    }

    /**
     * Every service, slug => label. Merges base + synced, sorted by label.
     *
     * @return array<string, string>
     */
    public static function services(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_SERVICES, function () {
                $synced = Setting::getValue(self::SERVICES_KEY, []);
                $merged = array_merge(self::baseServices(), is_array($synced) ? $synced : []);
                asort($merged, SORT_NATURAL | SORT_FLAG_CASE);

                return $merged;
            });
        } catch (\Throwable) {
            return self::baseServices();
        }
    }

    /** A friendly label for a service slug (falls back to a title-cased slug). */
    public static function serviceLabel(string $slug): string
    {
        return self::services()[$slug] ?? ucwords(str_replace(['_', '-'], ' ', $slug));
    }

    /**
     * Persist a synced overlay from the catalogue-sync command. Empty inputs are
     * ignored so a failed provider fetch never wipes the stored catalogue.
     *
     * @param  array<string, string>  $countries
     * @param  array<string, string>  $services
     */
    public static function storeSynced(array $countries, array $services): void
    {
        if ($countries !== []) {
            $existing = Setting::getValue(self::COUNTRIES_KEY, []);
            Setting::setValue(self::COUNTRIES_KEY, array_merge(is_array($existing) ? $existing : [], $countries), 'numbers', 'Synced number countries.');
        }
        if ($services !== []) {
            $existing = Setting::getValue(self::SERVICES_KEY, []);
            Setting::setValue(self::SERVICES_KEY, array_merge(is_array($existing) ? $existing : [], $services), 'numbers', 'Synced number services.');
        }
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_COUNTRIES);
        Cache::forget(self::CACHE_SERVICES);
    }

    public static function isCatalogueKey(string $key): bool
    {
        return $key === self::COUNTRIES_KEY || $key === self::SERVICES_KEY;
    }

    /**
     * A provider's live-discovered country map — OUR slug => THEIR country id
     * (e.g. HeroSMS's numeric SMS-Activate-style id). Sourced from the
     * provider's own API by name-matching (never guessed), so it is accurate
     * where it exists and simply absent otherwise; `country_map` in
     * config/services.php remains the manual operator fallback beneath it.
     *
     * @return array<string, string>
     */
    public static function providerCountryMap(string $provider): array
    {
        try {
            return Cache::rememberForever(self::CACHE_PROVIDER_MAP_PREFIX.$provider, function () use ($provider) {
                $map = Setting::getValue(self::PROVIDER_MAP_KEY_PREFIX.$provider, []);

                return is_array($map) ? $map : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Persist a provider's discovered country map, merged over whatever is
     * already stored (never shrinks — a partial/failed re-sync can't erase a
     * previously-confirmed mapping). Empty input is a safe no-op.
     *
     * @param  array<string, string>  $map
     */
    public static function storeProviderCountryMap(string $provider, array $map): void
    {
        if ($map === []) {
            return;
        }
        $existing = Setting::getValue(self::PROVIDER_MAP_KEY_PREFIX.$provider, []);
        Setting::setValue(
            self::PROVIDER_MAP_KEY_PREFIX.$provider,
            array_merge(is_array($existing) ? $existing : [], $map),
            'numbers',
            "Live-discovered country map for {$provider}.",
        );
        Cache::forget(self::CACHE_PROVIDER_MAP_PREFIX.$provider);
    }

    /**
     * A provider's live-discovered SERVICE map — OUR slug => THEIR service id
     * (SMSPool's numeric service IDs; OnlineSIM already uses slug-compatible
     * codes so it has little use for this, but the mechanism is provider-
     * agnostic). Same discipline as providerCountryMap(): sourced from the
     * provider's own API by name-matching, never guessed.
     *
     * @return array<string, string>
     */
    public static function providerServiceMap(string $provider): array
    {
        try {
            return Cache::rememberForever(self::CACHE_PROVIDER_SERVICE_MAP_PREFIX.$provider, function () use ($provider) {
                $map = Setting::getValue(self::PROVIDER_SERVICE_MAP_KEY_PREFIX.$provider, []);

                return is_array($map) ? $map : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Persist a provider's discovered service map, merged over whatever is
     * already stored (never shrinks). Empty input is a safe no-op.
     *
     * @param  array<string, string>  $map
     */
    public static function storeProviderServiceMap(string $provider, array $map): void
    {
        if ($map === []) {
            return;
        }
        $existing = Setting::getValue(self::PROVIDER_SERVICE_MAP_KEY_PREFIX.$provider, []);
        Setting::setValue(
            self::PROVIDER_SERVICE_MAP_KEY_PREFIX.$provider,
            array_merge(is_array($existing) ? $existing : [], $map),
            'numbers',
            "Live-discovered service map for {$provider}.",
        );
        Cache::forget(self::CACHE_PROVIDER_SERVICE_MAP_PREFIX.$provider);
    }

    /**
     * The static country base (5sim slugs). Broad on purpose so the picker is
     * never "limited"; the live sync extends it to the provider's full list.
     *
     * @return array<string, string>
     */
    public static function baseCountries(): array
    {
        return [
            'usa' => 'United States', 'england' => 'United Kingdom', 'canada' => 'Canada',
            'nigeria' => 'Nigeria', 'ghana' => 'Ghana', 'kenya' => 'Kenya', 'southafrica' => 'South Africa',
            'ivorycoast' => "Côte d'Ivoire", 'cameroon' => 'Cameroon', 'senegal' => 'Senegal',
            'tanzania' => 'Tanzania', 'uganda' => 'Uganda', 'rwanda' => 'Rwanda', 'ethiopia' => 'Ethiopia',
            'zambia' => 'Zambia', 'zimbabwe' => 'Zimbabwe', 'mozambique' => 'Mozambique', 'angola' => 'Angola',
            'morocco' => 'Morocco', 'algeria' => 'Algeria', 'tunisia' => 'Tunisia', 'egypt' => 'Egypt',
            'france' => 'France', 'germany' => 'Germany', 'spain' => 'Spain', 'italy' => 'Italy',
            'portugal' => 'Portugal', 'netherlands' => 'Netherlands', 'belgium' => 'Belgium',
            'ireland' => 'Ireland', 'sweden' => 'Sweden', 'norway' => 'Norway', 'denmark' => 'Denmark',
            'finland' => 'Finland', 'poland' => 'Poland', 'czech' => 'Czechia', 'austria' => 'Austria',
            'switzerland' => 'Switzerland', 'romania' => 'Romania', 'bulgaria' => 'Bulgaria',
            'hungary' => 'Hungary', 'greece' => 'Greece', 'ukraine' => 'Ukraine', 'russia' => 'Russia',
            'turkey' => 'Türkiye', 'serbia' => 'Serbia', 'croatia' => 'Croatia', 'lithuania' => 'Lithuania',
            'latvia' => 'Latvia', 'estonia' => 'Estonia', 'georgia' => 'Georgia', 'armenia' => 'Armenia',
            'azerbaijan' => 'Azerbaijan', 'kazakhstan' => 'Kazakhstan', 'uzbekistan' => 'Uzbekistan',
            'india' => 'India', 'pakistan' => 'Pakistan', 'bangladesh' => 'Bangladesh', 'srilanka' => 'Sri Lanka',
            'nepal' => 'Nepal', 'philippines' => 'Philippines', 'indonesia' => 'Indonesia', 'vietnam' => 'Vietnam',
            'thailand' => 'Thailand', 'malaysia' => 'Malaysia', 'singapore' => 'Singapore', 'cambodia' => 'Cambodia',
            'myanmar' => 'Myanmar', 'laos' => 'Laos', 'china' => 'China', 'hongkong' => 'Hong Kong',
            'taiwan' => 'Taiwan', 'japan' => 'Japan', 'southkorea' => 'South Korea', 'mongolia' => 'Mongolia',
            'uae' => 'United Arab Emirates', 'saudiarabia' => 'Saudi Arabia', 'qatar' => 'Qatar',
            'kuwait' => 'Kuwait', 'bahrain' => 'Bahrain', 'oman' => 'Oman', 'jordan' => 'Jordan',
            'lebanon' => 'Lebanon', 'iraq' => 'Iraq', 'israel' => 'Israel', 'palestine' => 'Palestine',
            'brazil' => 'Brazil', 'mexico' => 'Mexico', 'argentina' => 'Argentina', 'colombia' => 'Colombia',
            'chile' => 'Chile', 'peru' => 'Peru', 'venezuela' => 'Venezuela', 'ecuador' => 'Ecuador',
            'bolivia' => 'Bolivia', 'paraguay' => 'Paraguay', 'uruguay' => 'Uruguay', 'guatemala' => 'Guatemala',
            'honduras' => 'Honduras', 'panama' => 'Panama', 'costarica' => 'Costa Rica', 'dominicana' => 'Dominican Republic',
            'australia' => 'Australia', 'newzealand' => 'New Zealand',
            'afghanistan' => 'Afghanistan', 'iran' => 'Iran', 'syria' => 'Syria', 'yemen' => 'Yemen',
            'botswana' => 'Botswana', 'namibia' => 'Namibia', 'mauritius' => 'Mauritius', 'madagascar' => 'Madagascar',
            'benin' => 'Benin', 'togo' => 'Togo', 'mali' => 'Mali', 'burkinafaso' => 'Burkina Faso',
            'guinea' => 'Guinea', 'niger' => 'Niger', 'gabon' => 'Gabon', 'congo' => 'Congo',
        ];
    }

    /**
     * The static service base (provider product slugs => label). The most-used
     * OTP/verification services these providers carry; the live sync extends it.
     *
     * @return array<string, string>
     */
    public static function baseServices(): array
    {
        return [
            'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'google' => 'Google', 'facebook' => 'Facebook',
            'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'twitter' => 'X (Twitter)', 'discord' => 'Discord',
            'snapchat' => 'Snapchat', 'signal' => 'Signal', 'viber' => 'Viber', 'wechat' => 'WeChat',
            'line' => 'LINE', 'kakaotalk' => 'KakaoTalk', 'imo' => 'imo', 'skype' => 'Skype',
            'microsoft' => 'Microsoft', 'apple' => 'Apple', 'linkedin' => 'LinkedIn', 'yahoo' => 'Yahoo',
            'protonmail' => 'Proton Mail', 'amazon' => 'Amazon', 'netflix' => 'Netflix', 'spotify' => 'Spotify',
            'twitch' => 'Twitch', 'steam' => 'Steam', 'playstation' => 'PlayStation', 'xbox' => 'Xbox',
            'epicgames' => 'Epic Games', 'riotgames' => 'Riot Games', 'roblox' => 'Roblox', 'supercell' => 'Supercell',
            'uber' => 'Uber', 'bolt' => 'Bolt', 'lyft' => 'Lyft', 'grab' => 'Grab', 'gojek' => 'Gojek',
            'indriver' => 'inDrive', 'careem' => 'Careem', 'didi' => 'DiDi',
            'airbnb' => 'Airbnb', 'booking' => 'Booking.com', 'tinder' => 'Tinder', 'bumble' => 'Bumble',
            'badoo' => 'Badoo', 'hinge' => 'Hinge', 'grindr' => 'Grindr', 'okcupid' => 'OkCupid',
            'paypal' => 'PayPal', 'revolut' => 'Revolut', 'wise' => 'Wise', 'cashapp' => 'Cash App',
            'venmo' => 'Venmo', 'payoneer' => 'Payoneer', 'skrill' => 'Skrill', 'stripe' => 'Stripe',
            'binance' => 'Binance', 'coinbase' => 'Coinbase', 'kraken' => 'Kraken', 'kucoin' => 'KuCoin',
            'okx' => 'OKX', 'bybit' => 'Bybit', 'crypto' => 'Crypto.com', 'blockchain' => 'Blockchain',
            'paxful' => 'Paxful', 'bitget' => 'Bitget',
            'openai' => 'OpenAI', 'claude' => 'Claude', 'gemini' => 'Google Gemini', 'perplexity' => 'Perplexity',
            'github' => 'GitHub', 'gitlab' => 'GitLab', 'notion' => 'Notion', 'slack' => 'Slack',
            'zoom' => 'Zoom', 'dropbox' => 'Dropbox', 'adobe' => 'Adobe', 'canva' => 'Canva', 'figma' => 'Figma',
            'alibaba' => 'Alibaba', 'aliexpress' => 'AliExpress', 'shopee' => 'Shopee', 'lazada' => 'Lazada',
            'temu' => 'Temu', 'shein' => 'SHEIN', 'ebay' => 'eBay', 'walmart' => 'Walmart', 'etsy' => 'Etsy',
            'mercadolibre' => 'Mercado Libre', 'jumia' => 'Jumia', 'flutterwave' => 'Flutterwave',
            'paystack' => 'Paystack', 'opay' => 'OPay', 'palmpay' => 'PalmPay', 'kuda' => 'Kuda',
            'mpesa' => 'M-Pesa', 'chipper' => 'Chipper Cash', 'moniepoint' => 'Moniepoint',
            'yalla' => 'Yalla', 'clubhouse' => 'Clubhouse', 'threads' => 'Threads', 'mastodon' => 'Mastodon',
            'reddit' => 'Reddit', 'quora' => 'Quora', 'pinterest' => 'Pinterest', 'tumblr' => 'Tumblr',
            'deliveroo' => 'Deliveroo', 'doordash' => 'DoorDash', 'ubereats' => 'Uber Eats', 'glovo' => 'Glovo',
            'foodpanda' => 'foodpanda', 'talabat' => 'Talabat', 'zomato' => 'Zomato', 'swiggy' => 'Swiggy',
            'other' => 'Other service',
        ];
    }
}
