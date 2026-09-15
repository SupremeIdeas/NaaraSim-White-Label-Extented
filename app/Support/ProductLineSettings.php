<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Product-line CMS (BLUEPRINT-batch1-sections §4). Replaces the old shallow
 * p1..p4 marketing panels with six rich, admin-editable product entries — each
 * with a hero image, summary, an ordered set of "body blocks" (a 3-beat
 * friction → resolution → belonging arc), a small modal gallery, and a CTA.
 *
 * Stored as one JSON map under `marketing.product_lines`, following the same
 * Setting + Cache::rememberForever pattern as BrandSettings / PreloaderSettings.
 * Until an admin saves, DEFAULTS render — the live products reuse their existing
 * approved copy verbatim; Naara Connect and Naara Rent ship with DRAFT copy
 * (is_draft = true), pending owner approval, and Rent's rental-duration terms are
 * deliberately left unstated until confirmed.
 */
class ProductLineSettings
{
    private const CACHE_KEY = 'marketing.product_lines';

    private const SETTING_KEY = 'marketing.product_lines';

    /**
     * The six Naara product lines. Order here is the default display order.
     * `is_draft` marks copy awaiting owner sign-off (surfaced in the admin UI).
     * Verified against real feature code per the blueprint's product mapping.
     */
    public static function defaults(): array
    {
        return [
            [
                'slug' => 'naara-data', 'icon' => 'globe', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'Data & connectivity',
                'title' => BrandSettings::rebrand('Naara Data'),
                'summary' => 'Local data in 190+ countries, installed on your phone before you fly. No SIM cards, no airport counters, no roaming shocks — you land connected.',
                'cta_label' => 'Browse eSIM plans', 'cta_route' => 'catalogue',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'Every border used to mean the same scramble — a $40 airport SIM that barely worked, or roaming charges you dreaded all trip.'],
                    ['heading' => 'What happens now', 'text' => 'Your data plan is on your phone before you fly. You land, and you are already online — no counters, no swaps, no surprises.'],
                    ['heading' => 'Who it is for', 'text' => 'Built first for the African traveller crossing borders, so connectivity is the one thing you never have to solve again.'],
                ],
            ],
            [
                'slug' => 'naara-connect', 'icon' => 'signal', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'One eSIM, one number, everything included',
                'title' => BrandSettings::rebrand('Naara Connect'),
                'summary' => 'Calls, texts, and data on a single eSIM — with a real number attached. No juggling a data-only plan and a separate number app.',
                'cta_label' => BrandSettings::rebrand('Explore Naara Connect'), 'cta_route' => 'catalogue',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'Two apps for one trip — a data plan in one place, a number to actually be reachable in another.'],
                    ['heading' => 'What happens now', 'text' => 'One eSIM carries your calls, texts and data, with a real number attached. One thing to manage, not two.'],
                    ['heading' => 'How it differs', 'text' => BrandSettings::rebrand('Naara Data is data-only; Naara Line is a number without bundled data. Connect is the all-in-one for frequent travellers who want a single thing to manage.')],
                ],
            ],
            [
                'slug' => 'naara-verify', 'icon' => 'hash', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'Verification',
                'title' => BrandSettings::rebrand('Naara Verify'),
                'summary' => 'One-time codes for WhatsApp, Google, Facebook, Telegram and hundreds more services — delivered in seconds, refunded automatically if no code arrives.',
                'cta_label' => 'Get a verification number', 'cta_route' => 'numbers',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'Signing up abroad and the code never comes — or worse, handing your real number to an app you do not fully trust.'],
                    ['heading' => 'What happens now', 'text' => 'Grab a one-time number, receive the code in seconds, and if it never arrives you are refunded automatically.'],
                    ['heading' => 'Who it is for', 'text' => 'For the privacy-minded and the always-signing-up — keep your real number yours.'],
                ],
            ],
            [
                'slug' => 'naara-rent', 'icon' => 'refresh', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'A number for exactly as long as you need it',
                'title' => BrandSettings::rebrand('Naara Rent'),
                'summary' => 'Rent a number for a trip, a project, or a verification window that needs to outlast a single code — then let it go. No monthly commitment.',
                'cta_label' => 'Rent a number', 'cta_route' => 'numbers',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'A one-time code is too short and a permanent line is too much — when you only need a number for a little while.'],
                    ['heading' => 'What happens now', 'text' => 'Rent a number for exactly the window you need — a trip, a project, a sign-up that has to outlast a single code — then let it go.'],
                    // Durations stated to match what the providers actually support:
                    // long-term day/period rentals are a US (Getatext) capability; all
                    // other lanes are short-term at the provider's set period.
                    ['heading' => 'How long you can keep it', 'text' => 'US numbers rent long-term by the week, month, or quarter (1 week, 1 month or 3 months), with optional auto-renew. Numbers in other countries rent short-term for the provider\'s set period. You only ever pay retail for the window you choose.'],
                ],
            ],
            [
                'slug' => 'naara-line', 'icon' => 'phone', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'Your permanent second line',
                'title' => BrandSettings::rebrand('Naara Line'),
                'summary' => 'A permanent second line for calls and SMS that lives in the cloud. Perfect for business, travel, or keeping your personal number private.',
                'cta_label' => BrandSettings::rebrand('Explore Naara Line'), 'cta_route' => 'numbers',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'One number for work, one for home, one you gave a stranger once — and no clean way to keep them apart.'],
                    ['heading' => 'What happens now', 'text' => 'A permanent second line with its own calls and SMS, living in the cloud. Give it out freely; keep your real number private.'],
                    ['heading' => 'Who it is for', 'text' => 'For the business traveller and the boundary-keeper who want a number that is theirs for good.'],
                ],
            ],
            [
                'slug' => 'naara-gift', 'icon' => 'gift', 'hero_image' => '', 'is_draft' => false,
                'eyebrow' => 'Show up from anywhere',
                'title' => BrandSettings::rebrand('Naara Gift'),
                'summary' => 'Send digital gift cards for 1,000+ brands — shopping, airtime, streaming and games — to anyone, anywhere, delivered instantly by email or WhatsApp.',
                'cta_label' => BrandSettings::rebrand('Browse Naara Gift'), 'cta_route' => 'gift-cards',
                'modal_gallery' => [],
                'modal_blocks' => [
                    ['heading' => 'The old ritual', 'text' => 'Being far from home on the days that matter, with no easy way to actually show up.'],
                    ['heading' => 'What happens now', 'text' => 'Send a digital gift card for 1,000+ brands — shopping, airtime, streaming, games — delivered instantly by email or WhatsApp.'],
                    ['heading' => 'Who it is for', 'text' => BrandSettings::rebrand('The newest way Naara keeps you close to the people who matter, wherever you both are.')],
                ],
            ],
        ];
    }

    /** The ordered product list (admin-saved or defaults). */
    public static function products(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $saved = Setting::getValue(self::SETTING_KEY);

                return is_array($saved) && $saved !== [] ? array_values($saved) : self::defaults();
            } catch (\Throwable) {
                return self::defaults();
            }
        });
    }

    /**
     * Map the products to <x-storytelling-carousel> slide shape, resolving each
     * CTA to a URL (guests are sent to register, matching the old panel behaviour;
     * Naara Gift only links live when its feature is enabled).
     */
    public static function slides(): array
    {
        return array_map(function (array $p) {
            return [
                'image' => $p['hero_image'] ?? '',
                'eyebrow' => $p['eyebrow'] ?? '',
                'title' => $p['title'] ?? '',
                'body' => $p['summary'] ?? '',
                'cta_label' => $p['cta_label'] ?? null,
                'cta_url' => self::resolveCta($p),
                'modal_gallery' => $p['modal_gallery'] ?? [],
                'modal_blocks' => $p['modal_blocks'] ?? [],
            ];
        }, self::products());
    }

    /** Resolve a product's CTA route name to a URL, with sensible guards. */
    private static function resolveCta(array $p): string
    {
        if (! auth()->check()) {
            return Route::has('register') ? route('register') : '#';
        }
        $route = $p['cta_route'] ?? '';
        if ($route === 'gift-cards' && ! FeatureFlags::enabled('naara_gift')) {
            return Route::has('dashboard') ? route('dashboard') : '#';
        }
        if ($route !== '' && Route::has($route)) {
            return route($route);
        }

        return Route::has('dashboard') ? route('dashboard') : '#';
    }

    public static function save(array $products): void
    {
        Setting::setValue(self::SETTING_KEY, array_values($products), 'marketing', 'Product line CMS');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
