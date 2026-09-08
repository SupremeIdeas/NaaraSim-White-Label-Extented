<?php

namespace App\Support;

/**
 * The registry behind per-theme custom landing pages (owner request,
 * 2026-09-07). Mirrors SectionLibrary's own registry pattern (type => blade
 * + defaults) but scoped to `ThemePreset::sectionStyle('landing_hero')`
 * instead of the generic page-builder: each entry is a THEME-SPECIFIC,
 * hand-built landing page (structurally mimicking a real reference layout,
 * never a generic template) plus the small set of admin-editable content
 * fields that specific layout actually exposes.
 *
 * This is deliberately additive to — not a replacement for — the existing
 * SiteContent / PageBuilder homepage content systems. 'default' (i.e. no
 * entry here) means a theme keeps using those exactly as today. Only a
 * theme with a genuinely unique, custom-coded landing layout gets an entry.
 *
 * "we seed the extra controls as we are building... adopting the editor to
 * also learn for future tweak and extending layout capabilities" — this
 * registry IS that adoption point: `Admin\ThemePicker`'s landing-content
 * editor is entirely schema-driven off `fields()` below, so shipping a new
 * landing style for a future theme batch is just adding one array here —
 * the admin editor automatically grows a matching form, no UI code change.
 */
class LandingHeroLibrary
{
    public const IMAGE_RADIUS_OPTIONS = ['none' => 'None', 'md' => 'Rounded', 'xl' => 'Very rounded', 'full' => 'Circular'];

    public const IMAGE_POSITION_OPTIONS = ['left' => 'Left', 'right' => 'Right', 'center' => 'Center'];

    /**
     * @return array<string, array{blade: string, fields: array<int, array<string, mixed>>}>
     */
    public static function styles(): array
    {
        return [
            'neon-vertex' => [
                'blade' => 'marketing.theme-landing.neon-vertex',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'New · Naara 2.0'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connect. Roam. Disrupt distance.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'The all-in-one app to get data and a real number in 190+ countries, faster than ever.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Start roaming'],
                    // Owner rule (2026-09-07): "no place will be empty" — every
                    // image slot ships with a real photo by default rather than
                    // an empty gradient placeholder, until the admin uploads the
                    // real one. Owner rule, follow-up: that photo is downloaded
                    // once, converted to WebP, and committed under
                    // public/images/themes/ — never a live external hotlink.
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/phone-screen.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'xl'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Countries covered'],
                ],
            ],
            'midnight-signal' => [
                'blade' => 'marketing.theme-landing.midnight-signal',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Fly smarter with Naara'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Stay connected on every trip'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Get the cheapest local data and a real number, the moment you land — no roaming shock, no SIM swap.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Search plans now'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/earth-space.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'md'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'center'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '94%'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Coverage predicted before you land'],
                ],
            ],
            // Batch 2 (2026-09-07): 3 batch-1 themes complete their full
            // suite (aries-contrast, paperwhite, origin-bold already have
            // their own header/bottom_nav/login), 2 brand-new personas join
            // (solar-flare, noir-reserve).
            'aries-contrast' => [
                'blade' => 'marketing.theme-landing.aries-contrast',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'LIVE · 190+ MARKETS'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Your signal. Zero downtime.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Data and a real number, priced and delivered with no fine print — built for travellers who don\'t wait.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Lock in your plan'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/phone-screen.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'none'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '00:02:14'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Average activation time'],
                ],
            ],
            'paperwhite' => [
                'blade' => 'marketing.theme-landing.paperwhite',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'A quieter way to travel'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connectivity, without the noise.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'One app, one wallet, 190+ countries — nothing to configure, nothing to explain.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Begin'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/coworking-desk.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'none'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'center'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Countries, quietly covered'],
                ],
            ],
            'origin-bold' => [
                'blade' => 'marketing.theme-landing.origin-bold',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'BUILT BOLD'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Big signal. Bigger confidence.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'One wallet for data and numbers across 190+ countries — no small print, no soft edges.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Get loud, get connected'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/earth-space.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'none'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '2 min'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'To full activation'],
                ],
            ],
            'solar-flare' => [
                'blade' => 'marketing.theme-landing.solar-flare',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'LIVE · ON THE BOARD'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Never miss the connection.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Real-time coverage, real-time pricing — get on the board the moment you land, in 190+ countries.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Get in the game'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/team-coworking.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'none'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Networks on the board'],
                ],
            ],
            'noir-reserve' => [
                'blade' => 'marketing.theme-landing.noir-reserve',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Quiet, by design'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connectivity, reserved for those who notice.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'A single quiet wallet for data and numbers across 190+ countries — considered, not shouted about.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Reserve your line'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/coworking-desk.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'xl'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'left'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Destinations, discreetly covered'],
                ],
            ],
            // Batch 3 (2026-09-07): 5 brand-new personas, built from scratch.
            'aurora-shift' => [
                'blade' => 'marketing.theme-landing.aurora-shift',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'REAL-TIME · 190+ MARKETS'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connectivity, priced like capital.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'One wallet, live rates, zero markup surprises — the same discipline you expect from your bank, applied to data and numbers.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Open your wallet'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/earth-space.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'md'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Markets, one live rate card'],
                ],
            ],
            'sunset-transit' => [
                'blade' => 'marketing.theme-landing.sunset-transit',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'NOW BOARDING · 190+ COUNTRIES'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Your connection, cleared for departure.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Data and a real number, ready before wheels-up — no gate-side scramble, no roaming surprise on arrival.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Check in your plan'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/phone-screen.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'md'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => 'ON TIME'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Activation status, every trip'],
                ],
            ],
            'fintra-clean' => [
                'blade' => 'marketing.theme-landing.fintra-clean',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'AUDITED · 190+ COUNTRIES'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Every charge, itemised. Every time.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'A single ledger for data and numbers across 190+ countries — the rate you were quoted is the rate on the receipt.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'View the rate card'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/coworking-desk.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'none'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '0'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Hidden line items, ever'],
                ],
            ],
            'capable-mono' => [
                'blade' => 'marketing.theme-landing.capable-mono',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'CAPABLE · 190+ COUNTRIES'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Quiet by day. Unstoppable by night.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'One wallet for data and numbers, built to disappear into the background until the exact moment you need it.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Get capable'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/phone-screen.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'md'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Countries, one capable app'],
                ],
            ],
            'waitlisty-soft' => [
                'blade' => 'marketing.theme-landing.waitlisty-soft',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => "You're going to love this"],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connected, the friendly way.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Data and a real number for 190+ countries, set up in a few taps — no jargon, no fine print, just a warm "you\'re online."'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Say hello to Naara'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => asset('images/themes/shared/team-coworking.webp')],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'full'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Countries to say hi from'],
                ],
            ],
        ];
    }

    public static function has(string $style): bool
    {
        return array_key_exists($style, self::styles());
    }

    /** @return array<int, array<string, mixed>> */
    public static function fieldsFor(string $style): array
    {
        return self::styles()[$style]['fields'] ?? [];
    }

    public static function bladeFor(string $style): ?string
    {
        return self::styles()[$style]['blade'] ?? null;
    }

    /** Every field's default value, keyed by field key — the content shown before an admin edits anything. */
    public static function defaultsFor(string $style): array
    {
        return collect(self::fieldsFor($style))->mapWithKeys(fn ($f) => [$f['key'] => $f['default'] ?? null])->all();
    }
}
