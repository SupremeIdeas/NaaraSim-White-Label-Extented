<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed link-preview (Open Graph) images — what a chat app shows when
 * someone pastes a Naara link (owner request, 2026-09-08: the generic site
 * favicon showing up in WhatsApp previews for merchant invoice links isn't
 * the intended look). Three independently swappable contexts, each seeded with
 * a real shipped banner so a fresh install already looks intentional — same
 * "never a blank/generic placeholder" principle as BrandSettings' logo
 * fallbacks:
 *   - `default`   — every page that doesn't set its own og:image.
 *   - `invoice`   — the public merchant-invoice link (/i/{token}).
 *   - `referral`  — the homepage when opened via a referral link (?ref=...);
 *     covers both the user referral link (Referrals::mount()) and any
 *     merchant invite link, since both currently point at the homepage with
 *     a query string rather than a dedicated route.
 *
 * Values are public URLs (served from the public/Wasabi disk via
 * MediaStorage, same as BrandSettings), cached and busted on any save.
 */
class LinkPreviewSettings
{
    private const CACHE_KEY = 'link_preview.settings';

    public const CONTEXT_DEFAULT = 'default';

    public const CONTEXT_INVOICE = 'invoice';

    public const CONTEXT_REFERRAL = 'referral';

    /** @var list<string> */
    public const CONTEXTS = [self::CONTEXT_DEFAULT, self::CONTEXT_INVOICE, self::CONTEXT_REFERRAL];

    /** Setting keys this feature owns (for upload + cache-bust). */
    public const KEYS = [
        'link_preview.default',
        'link_preview.invoice',
        'link_preview.referral',
    ];

    /** Shipped defaults — real banners, never a blank slot. */
    private const SHIPPED_DEFAULTS = [
        self::CONTEXT_DEFAULT => '/images/marketing/banners/esim-virtual-numbers-banner.webp',
        self::CONTEXT_INVOICE => '/images/marketing/banners/invoice-banner.webp',
        self::CONTEXT_REFERRAL => '/images/marketing/banners/fast-payout-banner.webp',
    ];

    /**
     * @return array<string, string> context => URL
     *
     * Every page render touches this (via the layout's og:image fallback), so
     * it must never fail a request over a missing/unmigrated `settings` table
     * — same defensive shape as NumbersBento::cards(): fall back to the
     * shipped defaults rather than letting a DB hiccup break page rendering.
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $out = [];
                foreach (self::CONTEXTS as $context) {
                    $stored = (string) Setting::getValue("link_preview.{$context}", '');
                    $out[$context] = $stored !== '' ? $stored : self::SHIPPED_DEFAULTS[$context];
                }

                return $out;
            } catch (\Throwable) {
                return self::SHIPPED_DEFAULTS;
            }
        });
    }

    /** The absolute URL to use as og:image for a given context. Never empty. */
    public static function resolve(string $context): string
    {
        $all = self::all();
        $path = $all[$context] ?? self::SHIPPED_DEFAULTS[self::CONTEXT_DEFAULT];

        return str_starts_with($path, 'http') ? $path : url($path);
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
