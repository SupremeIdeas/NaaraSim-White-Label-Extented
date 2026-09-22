<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Frontend-UX-fix blueprint Phase G — generalizes the homepage's own
 * "More from Naara" banner carousel (owner request, 2026-09-08, previously
 * hardcoded to render only in `marketing/home/_banner_carousel.blade.php`)
 * into an admin-configurable placement system: the SAME slide set and the
 * SAME `<x-storytelling-carousel>` component, but now choosable per
 * PLACEMENT (where it renders) and DISPLAY STYLE (how much of each slide
 * shows), without any per-placement Blade duplication.
 *
 * Zero-behavior-change by default: an unconfigured install resolves
 * `marketing_home` to the exact enabled/display-style pair the homepage
 * banner already shipped with (falls back to the pre-existing
 * `home.banner_carousel_enabled` key so a value an admin already set there
 * is never silently lost), and resolves the brand-new `dashboard_footer`
 * placement to OFF — so nothing changes anywhere until an admin actively
 * turns it on.
 */
class BannerPlacements
{
    public const PLACEMENTS = [
        'marketing_home' => 'Homepage (public marketing site)',
        'dashboard_footer' => 'Dashboard footer (signed-in customers)',
    ];

    public const DISPLAY_STYLES = [
        'banner_only' => 'Banner only (image + title)',
        'banner_with_description' => 'Banner with description (image + eyebrow + title + body + CTA)',
    ];

    private const DEFAULTS = [
        'marketing_home' => ['enabled' => true, 'display_style' => 'banner_with_description'],
        'dashboard_footer' => ['enabled' => false, 'display_style' => 'banner_only'],
    ];

    /** @return array{enabled: bool, display_style: string} */
    public static function config(string $placement): array
    {
        $default = self::DEFAULTS[$placement] ?? ['enabled' => false, 'display_style' => 'banner_only'];

        $enabledDefault = $default['enabled'];
        if ($placement === 'marketing_home') {
            // Legacy key predates this registry (owner request, 2026-09-08) —
            // an admin who already flipped it keeps that exact value.
            $enabledDefault = (bool) Setting::getValue('home.banner_carousel_enabled', $enabledDefault);
        }

        return [
            'enabled' => (bool) Setting::getValue("banners.{$placement}.enabled", $enabledDefault),
            'display_style' => (string) Setting::getValue("banners.{$placement}.display_style", $default['display_style']),
        ];
    }

    public static function save(string $placement, bool $enabled, string $displayStyle): void
    {
        abort_unless(array_key_exists($placement, self::PLACEMENTS), 404);
        abort_unless(array_key_exists($displayStyle, self::DISPLAY_STYLES), 422);

        Setting::setValue("banners.{$placement}.enabled", $enabled, 'marketing');
        Setting::setValue("banners.{$placement}.display_style", $displayStyle, 'marketing');

        if ($placement === 'marketing_home') {
            // Keep the legacy key in step so any older code path still
            // reading it directly (and the pre-existing test suite) sees
            // the same value as the new one.
            Setting::setValue('home.banner_carousel_enabled', $enabled, 'marketing');
        }
    }

    /**
     * The shared "More from Naara" slide set (owner request, 2026-09-08) —
     * unchanged from the homepage's original hardcoded array, now reusable
     * by any placement. `banner_only` display style drops everything but
     * the image, title and CTA link so the trimmed-down slide never shows
     * an orphaned "read more" affordance for text it isn't displaying.
     *
     * @return list<array<string, mixed>>
     */
    public static function slides(string $displayStyle): array
    {
        $full = [
            [
                'image' => asset('images/marketing/banners/esim-virtual-numbers-banner.webp'),
                'eyebrow' => 'One platform',
                'title' => 'eSIM data + virtual numbers',
                'body' => 'Global eSIM data plans and virtual/SMS-verification numbers, in one app — no juggling providers.',
                'cta_label' => 'Browse plans',
                'cta_url' => route('catalogue'),
            ],
            [
                'image' => asset('images/marketing/banners/become-a-merchant-banner.webp'),
                'eyebrow' => 'Sell on Naara',
                'title' => 'Become a merchant',
                'body' => 'One-time registration, then sell eSIM to your own customers and earn on every sale, forever.',
                'cta_label' => 'Apply now',
                'cta_url' => route('merchant.apply'),
            ],
            [
                'image' => asset('images/marketing/banners/invoice-banner.webp'),
                'eyebrow' => 'For merchants',
                'title' => 'Send professional invoices',
                'body' => 'Bill your clients with a clean, shareable invoice link — they pay you directly, no middleman.',
                'cta_label' => 'Become a merchant',
                'cta_url' => route('merchant.apply'),
            ],
            [
                'image' => asset('images/marketing/banners/fast-payout-banner.webp'),
                'eyebrow' => 'No delay',
                'title' => 'Fast payouts',
                'body' => 'Referrals, merchant sales, and NaaraCredit — your earnings move to you on schedule, every time.',
                'cta_label' => 'See how it works',
                'cta_url' => route('referrals'),
            ],
            [
                'image' => asset('images/marketing/banners/bank-account-setup-banner.webp'),
                'eyebrow' => 'Set up in minutes',
                'title' => 'Link your bank account',
                'body' => 'Automated bank verification means your payout details are ready in minutes, not days.',
                'cta_label' => 'Apply as a merchant',
                'cta_url' => route('merchant.apply'),
            ],
        ];

        if ($displayStyle !== 'banner_only') {
            return $full;
        }

        return array_map(fn (array $s) => [
            'image' => $s['image'],
            'title' => $s['title'],
        ], $full);
    }
}
