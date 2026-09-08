<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Site chrome (Module 28) — admin-managed auth media panel + assignable footer.
 *
 * The auth panel is the visual half of the two-column login/register layout:
 * an admin-uploaded image (WebP/JPEG) or a short muted video, with a headline
 * and subtext. The footer is fully admin-assignable: any number of link columns
 * (each a heading + label/url links) plus a legal-links row, shown across the
 * marketing, auth and legal pages. "Supreme Ideas Agency" attribution is a
 * brand constant and always rendered — it is never removable from the footer.
 *
 * Everything falls back to sensible defaults so a fresh install looks finished.
 * Cached; busted on any site.auth.* / site.footer.* save (AppServiceProvider).
 */
class SiteChrome
{
    private const CACHE_KEY = 'site.chrome.v1';

    public static function isChromeKey(string $key): bool
    {
        return str_starts_with($key, 'site.auth.') || str_starts_with($key, 'site.footer.');
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'auth' => [
                        'style' => Setting::getValue('site.auth.style', 'auto') ?: 'auto',
                        'media_type' => Setting::getValue('site.auth.media_type', 'image') ?: 'image',
                        'media_url' => (string) Setting::getValue('site.auth.media_url', ''),
                        'poster_url' => (string) Setting::getValue('site.auth.poster_url', ''),
                        'headline' => (string) (Setting::getValue('site.auth.headline') ?: self::defaultAuth()['headline']),
                        'subtext' => (string) (Setting::getValue('site.auth.subtext') ?: self::defaultAuth()['subtext']),
                    ],
                    'footer_columns' => Setting::getValue('site.footer.columns', null) ?: self::defaultColumns(),
                    'footer_legal' => Setting::getValue('site.footer.legal', null) ?: self::defaultLegal(),
                ];
            } catch (\Throwable) {
                return [
                    'auth' => self::defaultAuth(),
                    'footer_columns' => self::defaultColumns(),
                    'footer_legal' => self::defaultLegal(),
                ];
            }
        });
    }

    /** @return array<string, string> */
    public static function authPanel(): array
    {
        return self::all()['auth'];
    }

    /** True once the admin has uploaded panel media (else a branded gradient shows). */
    public static function hasAuthMedia(): bool
    {
        return self::authPanel()['media_url'] !== '';
    }

    /** @return list<array{heading: string, links: list<array{label: string, url: string}>}> */
    public static function footerColumns(): array
    {
        return self::all()['footer_columns'];
    }

    /** @return list<array{label: string, url: string}> */
    public static function footerLegal(): array
    {
        return self::all()['footer_legal'];
    }

    /** @return array<string, string> */
    public static function defaultAuth(): array
    {
        return [
            'style' => 'auto',           // auto | webgl | image (login page treatment)
            'media_type' => 'image',
            'media_url' => '',
            'poster_url' => '',
            'headline' => 'Stay Connected. No Borders. No Swaps.',
            'subtext' => 'eSIM data and phone numbers for 190+ countries — in one wallet. Installed before you fly, connected the moment you land.',
        ];
    }

    /** @return list<array{heading: string, links: list<array{label: string, url: string}>}> */
    public static function defaultColumns(): array
    {
        return [
            ['heading' => 'Product', 'links' => [
                ['label' => 'How It Works', 'url' => '/how-it-works'],
                ['label' => 'Browse Plans', 'url' => '/catalogue'],
                ['label' => 'Device Compatibility', 'url' => '/how-it-works#compatibility'],
                ['label' => 'Help Center', 'url' => '/faq'],
            ]],
            ['heading' => 'Company', 'links' => [
                ['label' => 'About Us', 'url' => '/about'],
                ['label' => 'Blog', 'url' => '/blog'],
                ['label' => 'Contact', 'url' => '/contact'],
                ['label' => 'Legal & Policies', 'url' => '/legal'],
            ]],
        ];
    }

    /** @return list<array{label: string, url: string}> */
    public static function defaultLegal(): array
    {
        return [
            ['label' => 'Terms', 'url' => '/legal/terms'],
            ['label' => 'Privacy', 'url' => '/legal/privacy'],
            ['label' => 'Refunds', 'url' => '/legal/refund'],
            ['label' => 'Status', 'url' => '/status'],
        ];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
