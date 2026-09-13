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
 * brand constant and always rendered — it is never removable from the footer;
 * only its PHRASING is admin-configurable (theme-integrity blueprint §2), and
 * the name always links to supremeideas.agency regardless of phrasing.
 *
 * The credit line itself used to be duplicated verbatim across site-footer.blade.php
 * and all 12 theme-sections/footer/*.blade.php variants (2026-09-13 cleanup) — it
 * now lives in ONE place (`<x-footer-credit>`, fed by this class), which is why a
 * white-label default phrasing change never needs touching more than one Setting.
 *
 * Everything falls back to sensible defaults so a fresh install looks finished.
 * Cached; busted on any site.auth.* / site.footer.* save (AppServiceProvider).
 */
class SiteChrome
{
    private const CACHE_KEY = 'site.chrome.v1';

    public const AGENCY_NAME = 'Supreme Ideas Agency';

    public const AGENCY_URL = 'https://supremeideas.agency';

    public const CREDIT_PRODUCT_OF = 'product_of';

    public const CREDIT_MADE_WITH_LOVE = 'made_with_love';

    public const CREDIT_CUSTOM = 'custom';

    public const CREDIT_PHRASINGS = [self::CREDIT_PRODUCT_OF, self::CREDIT_MADE_WITH_LOVE, self::CREDIT_CUSTOM];

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
                    'footer_credit_phrasing' => self::normalizedPhrasing(Setting::getValue('site.footer.credit_phrasing')),
                    'footer_credit_custom_text' => (string) Setting::getValue('site.footer.credit_custom_text', ''),
                ];
            } catch (\Throwable) {
                return [
                    'auth' => self::defaultAuth(),
                    'footer_columns' => self::defaultColumns(),
                    'footer_legal' => self::defaultLegal(),
                    'footer_credit_phrasing' => self::defaultCreditPhrasing(),
                    'footer_credit_custom_text' => '',
                ];
            }
        });
    }

    /**
     * The credit phrasing actually in effect: an explicit admin choice, or —
     * on a fresh install that has never touched this setting — a
     * repo-appropriate default. Same mechanism everywhere; only the SEEDED
     * DEFAULT differs (blueprint §2.3: master gets one phrasing, either
     * white-label fork gets the other, at fork-config time). Reuses the same
     * product-identity signal Batch 8's FeatureEntitlements already
     * established — no new per-repo branch, this is data, not code.
     */
    private static function normalizedPhrasing(mixed $stored): string
    {
        if (is_string($stored) && in_array($stored, self::CREDIT_PHRASINGS, true)) {
            return $stored;
        }

        return self::defaultCreditPhrasing();
    }

    private static function defaultCreditPhrasing(): string
    {
        return FeatureEntitlements::isMaster() ? self::CREDIT_PRODUCT_OF : self::CREDIT_MADE_WITH_LOVE;
    }

    /** The credit line's phrasing choice (blueprint §2): 'product_of' | 'made_with_love' | 'custom'. */
    public static function footerCreditPhrasing(): string
    {
        return self::all()['footer_credit_phrasing'];
    }

    /** Only meaningful when footerCreditPhrasing() === CREDIT_CUSTOM. Must contain the literal '{agency}' placeholder — validated on save (SiteChromePage). */
    public static function footerCreditCustomText(): string
    {
        return self::all()['footer_credit_custom_text'];
    }

    /**
     * The credit sentence split around the agency name, so the caller (the
     * shared `<x-footer-credit>` component) can wrap ONLY the name itself in
     * the real supremeideas.agency link — the surrounding copy is plain text.
     *
     * @return array{prefix: string, suffix: string}
     */
    public static function footerCreditParts(): array
    {
        return match (self::footerCreditPhrasing()) {
            self::CREDIT_MADE_WITH_LOVE => ['prefix' => 'Made with love by ', 'suffix' => '.'],
            self::CREDIT_CUSTOM => self::splitCustomCredit(self::footerCreditCustomText()),
            default => ['prefix' => 'A product of ', 'suffix' => '.'],
        };
    }

    /** @return array{prefix: string, suffix: string} */
    private static function splitCustomCredit(string $text): array
    {
        if (! str_contains($text, '{agency}')) {
            // Malformed/empty custom text (shouldn't happen past SiteChromePage's
            // validation, but this class must still degrade safely on its own) —
            // fail open to the standard phrasing rather than dropping the
            // attribution or leaving a literal '{agency}' token on the page.
            return ['prefix' => 'A product of ', 'suffix' => '.'];
        }

        [$prefix, $suffix] = explode('{agency}', $text, 2);

        return ['prefix' => $prefix, 'suffix' => $suffix];
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
