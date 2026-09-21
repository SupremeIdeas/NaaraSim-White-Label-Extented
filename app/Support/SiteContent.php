<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * CMS content for the public marketing pages (Module 27). The brand copy
 * (from the owner's "Complete Brand Copy Document") ships as code defaults;
 * the admin Pages editor stores per-field overrides, a visible flag, an
 * order, and an optional section image in ONE Setting row per page
 * (site.page.{page}), merged over the defaults at read time. Cached; busted
 * on any site.* save. So the marketing site works out of the box and is
 * fully editable with no redeploy.
 */
class SiteContent
{
    public const PAGES = ['home', 'about', 'how-it-works', 'contact', 'faq'];

    /**
     * PAGES minus any page that's master-only. Today that's just 'faq'
     * (owner decision, 2026-09-21 — the dedicated FAQ page's own route
     * 404s on a white-label fork, so its admin editor tab and page-select
     * shouldn't be reachable there either). Use this instead of the raw
     * PAGES const anywhere an admin picks a page to edit.
     *
     * @return list<string>
     */
    public static function editablePages(): array
    {
        return FeatureEntitlements::isMaster()
            ? self::PAGES
            : array_values(array_diff(self::PAGES, ['faq']));
    }

    /**
     * Sections that are self-contained and safe to reuse on ANY marketing page
     * (BUILD: reusable sections). Each renders through a shared partial —
     * `resources/views/marketing/sections/{key}.blade.php` — rather than a
     * page-specific one, so a copy of it renders identically wherever it lands.
     * Page-bespoke sections (hero copy, the contact form, …) are NOT portable.
     *
     * @var list<string>
     */
    public const PORTABLE_SECTIONS = ['audiences'];

    /**
     * The canonical default fields for a portable section, wherever it is
     * defined in the shipped copy (portable sections live under one home page by
     * default). Used so a COPIED instance on another page still knows its field
     * shape — for editing, diffing and reset.
     *
     * @return array<string, string>
     */
    public static function portableDefaults(string $key): array
    {
        foreach (self::defaults() as $sections) {
            if (isset($sections[$key])) {
                return $sections[$key];
            }
        }

        return [];
    }

    /**
     * The shipped copy. page => section => [fields]. Reserved keys the editor
     * adds per section: visible (bool), order (int), image (url).
     * List-ish content is kept as numbered fields so the editor stays a flat,
     * obvious form for a non-technical admin.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function defaults(): array
    {
        return [
            'home' => [
                'hero' => [
                    'eyebrow' => 'eSIM for Global Africans',
                    'headline' => 'Land Anywhere. Connect Instantly.',
                    'subheadline' => 'NaaraSim gives you a local data plan in 190+ countries — activated on your phone before you leave home. No SIM cards. No airport counters. No roaming shocks.',
                    'cta_primary' => 'Get Your eSIM Now',
                    'cta_secondary' => 'See How It Works',
                    'social_proof' => 'Trusted by travelers from Lagos, Accra, Nairobi and beyond · 190+ countries covered · Activates in under 3 minutes',
                    // BUILD-13 (marketing): a real, visible product/device-mockup
                    // shown BELOW the description and ABOVE the CTA row — separate
                    // from the full-bleed backdrop `image` field. Empty by default.
                    'showcase_image' => '',
                    'stat_1' => '190+ Countries', 'stat_2' => '3 Min Setup', 'stat_3' => '0 Physical SIM', 'stat_4' => '24/7 Support',
                ],
                'how' => [
                    'eyebrow' => 'Simple by Design',
                    'headline' => 'Connected in Three Steps',
                    'subtext' => 'No store visits. No paperwork. No waiting. Just a phone and two minutes.',
                    'step_1_title' => 'Pick Your Destination',
                    'step_1_text' => "Choose the country or region you're traveling to and select a data plan that fits your trip — from a weekend pass to a 30-day plan.",
                    'step_1_image' => '/images/steps/choose-destination.webp',
                    'step_2_title' => 'Select Your Data Plan',
                    'step_2_text' => 'Compare local, regional and global plans by size and duration, then pay securely. Your eSIM QR code lands in your inbox the moment you check out.',
                    'step_2_image' => '/images/steps/select-data-plan.webp',
                    'step_3_title' => 'Activate on Your Phone',
                    'step_3_text' => 'Scan the QR code or follow our guided setup — the eSIM installs in about two minutes. The moment you land, NaaraSim connects to local networks automatically.',
                    'step_3_image' => '/images/steps/activate-on-phone.webp',
                    'cta' => "Start Now — It's Instant",
                ],
                'features' => [
                    'eyebrow' => 'Built Different',
                    'headline' => 'Why Travelers Choose NaaraSim',
                    'f1_title' => 'Instant Digital Delivery',
                    'f1_text' => 'No waiting for shipping. Your eSIM arrives in your email the moment you pay. Activate it from anywhere in the world.',
                    'f2_title' => 'Keep Your Number',
                    'f2_text' => 'Your NaaraSim runs alongside your regular SIM. Calls and texts still come through your home number. NaaraSim handles your data.',
                    'f3_title' => '190+ Countries, One Platform',
                    'f3_text' => "Local, regional, and global plans available. Whether you're crossing one border or ten, there's a NaaraSim plan for your route.",
                    'f4_title' => 'No Roaming Bills',
                    'f4_text' => "You know exactly what you're paying before you travel. Fixed plan. No surprises. Your network back home stays off data abroad.",
                    'f5_title' => 'Top Up Anytime',
                    'f5_text' => 'Running low mid-trip? Add more data from the app without buying a new plan. Your eSIM stays active, your connection stays strong.',
                    'f6_title' => 'Built for African Travelers',
                    'f6_text' => 'NaaraSim was designed with the African traveler in mind. Support that understands your journey. Pricing that respects your reality.',
                ],
                'products' => [
                    'eyebrow' => 'One Family, Everything Connected',
                    'headline' => 'What Naara Gives You',
                    'subtext' => 'Naara is the family. NaaraSim keeps you connected — data and numbers across 190+ countries. Naara Gift lets you send the brands people love. One account, one wallet, one Naara.',
                    'p1_title' => 'eSIM Data Plans',
                    'p1_text' => 'Local data in 190+ countries, installed on your phone before you fly. No SIM cards, no airport counters, no roaming shocks — you land connected.',
                    'p1_cta' => 'Browse eSIM plans',
                    'p2_title' => 'Verification Numbers',
                    'p2_text' => 'One-time codes for WhatsApp, Google, Facebook, Telegram and hundreds more services — delivered in seconds, refunded automatically if no code arrives.',
                    'p2_cta' => 'Get a verification number',
                    'p3_title' => 'Virtual Numbers',
                    'p3_text' => 'A permanent second line for calls and SMS that lives in the cloud. Perfect for business, travel, or keeping your personal number private.',
                    'p3_cta' => 'Explore virtual numbers',
                    'p4_title' => 'Naara Gift Cards',
                    'p4_text' => 'Send digital gift cards for 1,000+ brands — shopping, airtime, streaming and games — to anyone, anywhere, delivered instantly by email or WhatsApp. The newest way Naara keeps you close to the people who matter.',
                    'p4_cta' => 'Browse Naara Gift',
                ],
                // "Who Naara is for" audience tabs (BUILD-12). Section header text
                // is admin-editable here; the six panels' approved copy lives in
                // the partial (finished copy). The six *_image fields are
                // admin-swappable via the SiteEditor inline-artwork uploader.
                'audiences' => [
                    'eyebrow' => 'Made for the way you move',
                    'headline' => 'Who Naara Is For',
                    'subtext' => 'One connectivity family, built for every kind of border-crosser. Find yourself below.',
                    'travelers_image' => '/images/audiences/naara-leisure-traveler.webp',
                    'business_image' => '/images/audiences/naara-business-professionals.webp',
                    'entrepreneurs_image' => '/images/audiences/naara-software-engineer.webp',
                    'creators_image' => '/images/audiences/naara-travel-creator.webp',
                    'privacy_image' => '/images/audiences/naara-secure-professional.webp',
                    'families_image' => '/images/audiences/naara-staying-connected.webp',
                ],
                'coverage' => [
                    'eyebrow' => "Wherever You're Going",
                    'headline' => '190+ Countries. One eSIM.',
                    'text' => 'From London to Lagos, Dubai to Dakar, Toronto to Tokyo — NaaraSim connects you to premium local networks the moment you land. We cover every continent, every major travel corridor, every destination on your bucket list.',
                    'highlights' => 'Europe · North America · Asia Pacific · Middle East · Latin America · Africa',
                    'callout' => 'Planning a multi-country trip? Our regional plans keep you connected across entire continents without switching plans.',
                    'cta' => 'Check Coverage for Your Destination',
                ],
                'pricing' => [
                    'eyebrow' => 'Transparent Pricing',
                    'headline' => 'Plans That Make Sense',
                    'text' => "No monthly fees. No contracts. Pay for exactly what you need — whether that's a 3-day pass or a 30-day global plan. Every plan includes instant activation and 24/7 support.",
                    'callout' => 'Plans start from $5 · No hidden fees · Cancel anytime',
                    'cta' => 'See All Plans',
                    'trust' => 'You only pay when you travel. No subscription required.',
                ],
                'testimonials' => [
                    'eyebrow' => 'Real Travelers, Real Connections',
                    'headline' => 'What Our Customers Say',
                    't1_name' => 'Chidinma O.', 't1_route' => 'Lagos → London',
                    't1_quote' => "I set up my NaaraSim the night before my flight and by the time I landed at Heathrow, my phone was already showing full bars. I didn't even notice the moment it connected. That's exactly how it should work.",
                    't2_name' => 'Kwame A.', 't2_route' => 'Accra → Dubai',
                    't2_quote' => "I've used three different eSIM apps before NaaraSim. None of them felt like they were made for me. This one actually works in the countries I travel to, and the top-up process is so simple I did it in a taxi.",
                    't3_name' => 'Amara T.', 't3_route' => 'Nairobi → Toronto',
                    't3_quote' => 'The setup took literally two minutes. My previous experience with roaming was painful — surprise bills, dead zones, hours on the phone with customer service. NaaraSim solved all of that in one purchase.',
                ],
                'faq' => [
                    'headline' => 'Questions We Hear a Lot',
                    'q1' => 'Does my phone support eSIM?',
                    'a1' => "Most phones made after 2019 support eSIM — including iPhone XS and later, Samsung Galaxy S20 and later, Google Pixel 3 and later, and many others. Check our compatibility list before purchasing. If your device supports eSIM and isn't carrier-locked, you're good to go.",
                    'q2' => 'Can I use NaaraSim and keep my regular number?',
                    'a2' => 'Yes. NaaraSim runs as a second SIM alongside your physical SIM. Calls and texts still come to your regular number. NaaraSim handles your data connection abroad.',
                    'q3' => 'When does my plan start?',
                    'a3' => "Your plan activates when your device connects to a local network at your destination — not when you purchase. So you won't burn data sitting at home.",
                    'q4' => 'What happens if I run out of data?',
                    'a4' => 'You can top up your existing plan directly from your account dashboard or purchase a new plan. Your eSIM stays installed — no need to re-scan anything.',
                    'q5' => 'Is NaaraSim available in Nigeria?',
                    'a5' => 'NaaraSim is designed for travelers going international. You purchase your plan in Nigeria before you travel, activate it on your phone, and it works the moment you land in your destination country.',
                    'q6' => 'What is Naara — and how is it different from NaaraSim?',
                    'a6' => 'Naara is the family brand — the name that ties everything together. NaaraSim is our connectivity product: eSIM data plans and virtual/verification numbers across 190+ countries. Naara Gift is our gift-card store. Same account, same wallet, same team you already trust — Naara is simply the umbrella as we grow beyond connectivity.',
                    'q7' => 'What is Naara Gift?',
                    'a7' => 'Naara Gift lets you buy and send digital gift cards for over a thousand brands — shopping, airtime, streaming, games and more — delivered instantly by email or WhatsApp. It runs on the same secure wallet and honest pricing as the rest of Naara: what you see is what you pay, every time.',
                    'q8' => 'Can one Naara account handle eSIMs and gift cards together?',
                    'a8' => 'Yes. Your Naara wallet and profile work across everything. Top up once and spend it on eSIM data, numbers, or Naara Gift — one login, one balance, no juggling separate apps.',
                    'q9' => 'How are Naara Gift cards delivered, and can they be refunded?',
                    'a9' => 'Instantly. The moment your purchase is confirmed, the code, redemption link or account credit is delivered on-screen and can be forwarded by email or WhatsApp. Gift cards are final once delivered, so every purchase is confirmed clearly before you pay — and if a card ever fails to deliver, your wallet is refunded automatically.',
                    'cta' => 'Still have questions? Our support team responds within minutes — not hours.',
                ],
                'cta' => [
                    'headline' => 'Your Next Trip Starts Here',
                    'subtext' => "Stop worrying about connectivity and start focusing on why you're traveling. Get your NaaraSim before you leave — and land ready.",
                    'cta_primary' => 'Get Connected Now',
                    'cta_secondary' => 'Browse Plans',
                    'trust' => 'Instant activation · No contracts · 190+ countries · 24/7 support',
                ],
            ],
            'faq' => [
                'hero' => [
                    'eyebrow' => 'Support',
                    'headline' => 'Frequently Asked Questions',
                    'subtext' => 'Everything you need to know about eSIMs, numbers, payments and more — the same answers as on our homepage, always in sync.',
                ],
            ],
            'about' => [
                'hero' => [
                    'eyebrow' => 'Our Story',
                    'headline' => 'We Built the eSIM We Wished Existed',
                    'subtext' => "NaaraSim didn't start in a Silicon Valley boardroom. It started with a missed meeting, a dead phone, and a $40 airport SIM card that barely worked. There had to be a better way.",
                ],
                'story' => [
                    'headline' => 'The Full Story',
                    'p1' => 'Every African who travels internationally knows the ritual. You land in a new country. Before you can breathe, you\'re calculating — which SIM vendor is least likely to scam you, which network covers your hotel, how much data is enough for a week. Connectivity, which should be invisible, becomes the first problem you solve.',
                    'p2' => 'NaaraSim was founded to end that ritual. We\'re a product of Supreme Ideas Agency — a brand and digital technology company based in Onitsha, Nigeria. When we started looking for an eSIM product that genuinely served African travelers, we found plenty of options built for European backpackers and American business travelers. Nothing that started from our reality. So we built NaaraSim.',
                    'p3' => '"Naara" means dawn in several West African traditions — the moment a new signal rises, the moment clarity breaks through. That\'s what NaaraSim is: your first clear signal, wherever you land.',
                    'p4' => 'We\'re not trying to be the biggest eSIM company in the world. We\'re trying to be the best one for our people — Africans traveling for business, for family, for opportunity, for joy. NaaraSim runs on premium global infrastructure covering 190+ countries, with a setup experience obsessed-over so it takes under three minutes.',
                ],
                'mission' => [
                    'headline' => "What We're Here to Do",
                    'mission_title' => 'Our Mission',
                    'mission' => 'To make global connectivity instant, affordable, and effortless for every African crossing a border — so they can focus on the journey, not the logistics of staying online.',
                    'vision_title' => 'Our Vision',
                    'vision' => 'A continent of travelers who move through the world as fluidly as their ambitions carry them. No connectivity barriers. No roaming anxiety. Just Africans showing up fully, everywhere they go.',
                    'belief_title' => 'Our Belief',
                    'belief' => "Connectivity is not a luxury. It's infrastructure. And every traveler — regardless of where they come from — deserves to land somewhere new and simply be online.",
                ],
                'values' => [
                    'headline' => 'What We Stand For',
                    'v1_title' => 'Clarity',
                    'v1_text' => 'We write every plan description, every support message, and every piece of our interface so that a first-time eSIM user understands exactly what they\'re getting. No telecom jargon. No hidden fees.',
                    'v2_title' => 'Speed',
                    'v2_text' => 'Your time is your most valuable resource. NaaraSim is designed so that from the moment you decide to buy to the moment you\'re connected takes less time than it takes to order a coffee.',
                    'v3_title' => 'Belonging',
                    'v3_text' => 'We built this for African travelers first. Our support understands your travel patterns, our pricing respects your budget reality, and our coverage prioritizes the corridors African professionals actually use.',
                    'v4_title' => 'Reliability',
                    'v4_text' => "When you're in a foreign city trying to close a deal, a dropped connection isn't just inconvenient — it's costly. NaaraSim runs on infrastructure that prioritizes uptime above everything.",
                ],
                'team' => [
                    'headline' => 'The People Behind NaaraSim',
                    'founder_name' => 'Frank Charles Ebubedike',
                    'founder_title' => 'Founder & CEO, NaaraSim · CEO, Supreme Ideas Agency',
                    'founder_bio' => "Frank is a brand strategist and digital product builder who has spent years helping businesses across Africa build meaningful presences online. NaaraSim is his answer to a problem he's experienced personally: the unnecessary friction of staying connected while traveling as an African professional. He built the product he couldn't find — designed for the traveler he knows best.",
                    'company' => 'NaaraSim is a product of Supreme Ideas Agency — a brand and digital strategy company based in Onitsha, Nigeria, building purposeful digital products for businesses across Africa and globally.',
                ],
            ],
            'how-it-works' => [
                'hero' => [
                    'headline' => 'From Purchase to Connected in Under 3 Minutes',
                    'subtext' => "NaaraSim is built to be simple. Here's exactly how it works — no technical knowledge required.",
                    'proof' => 'No store visits · No physical SIM · No roaming setup · Works on most modern smartphones',
                ],
                'steps' => [
                    'headline' => 'The Full Step-by-Step',
                    's1_title' => 'Check Your Device',
                    's1_text' => 'Before purchasing, confirm your phone supports eSIM and isn\'t carrier-locked. Most phones made after 2019 are eSIM-ready. Use our compatibility checker or look for "eSIM" in your phone\'s settings under Mobile Data.',
                    's2_title' => 'Choose Your Plan',
                    's2_text' => 'Browse plans by destination — local (one country), regional (a group of countries), or global. Pick the data size and duration that matches your trip. Plans start from 1GB.',
                    's3_title' => 'Complete Your Purchase',
                    's3_text' => 'Pay securely using your card or mobile payment method. Your eSIM QR code and activation instructions are sent to your email immediately after payment. No waiting.',
                    's4_title' => 'Install Before You Travel',
                    's4_text' => 'Scan the QR code from your email or go to Settings → Mobile Data → Add eSIM. The installation takes about 90 seconds. Do this while you still have Wi-Fi or home network access.',
                    's5_title' => 'Set NaaraSim as Your Data SIM',
                    's5_text' => 'After installation, set NaaraSim as your preferred data SIM. Keep your home SIM active for calls and texts.',
                    's6_title' => 'Land and Connect',
                    's6_text' => 'When you arrive, your phone automatically connects to a local network. Turn off data roaming on your home SIM to avoid carrier charges. NaaraSim handles everything else.',
                    's7_title' => 'Top Up If Needed',
                    's7_text' => 'Running low on data? Log in to your NaaraSim account and add more data to your existing plan without reinstalling or rescanning anything.',
                ],
                'compatibility' => [
                    'headline' => 'Does Your Phone Support NaaraSim?',
                    'intro' => "NaaraSim works on any eSIM-compatible device that isn't carrier-locked. Here's what to look for:",
                    'devices' => 'iPhone XS and later · Samsung Galaxy S20 and later, Z Fold/Flip · Google Pixel 3 and later · most flagship Androids released after 2020',
                    'check_ios' => 'iPhone: Settings → Cellular → Add Cellular Plan — if you see this option, you\'re eSIM-ready.',
                    'check_android' => 'Android: Settings → Network & Internet → SIMs → Add SIM — look for the eSIM option.',
                    'note' => "Your device must not be carrier-locked, and some regional phone variants don't include eSIM hardware. Not sure? Use the compatibility checker or ask our support — we'll confirm in minutes.",
                    'cta' => 'Check My Device',
                ],
                'support' => [
                    'headline' => "We're Here If You Need Us",
                    'text' => "Most NaaraSim setups go smoothly without any help. But travel is unpredictable, and we know that. If something doesn't work — a device issue, a network problem, or a simple question — our support team responds within minutes, not hours.",
                    'promise' => 'We don\'t close support tickets until your issue is actually resolved. Not just "responded to." Resolved.',
                ],
            ],
            'contact' => [
                'hero' => [
                    'headline' => 'We Actually Respond',
                    'subtext' => "Got a question before you buy? Need help with your setup? We're here. Real support from a real team — not a chatbot running in circles.",
                    'promise' => 'Average response time: under 2 hours',
                ],
                'form' => [
                    'headline' => 'Send Us a Message',
                    'success' => "We got your message. Someone from our team will respond within 2 hours. Check your spam folder if you don't hear from us — we'll be there.",
                    'disclaimer' => "We don't share your information. Ever.",
                ],
                'channels' => [
                    'headline' => 'Other Ways to Reach Us',
                    'chat_title' => 'Chat With Us',
                    'chat_text' => 'The fastest way to get help. Signed-in customers get our assistant plus a real human hand-off, right from the Help & Support menu.',
                    'email_title' => 'Email Support',
                    'email_text' => 'For detailed questions, installation screenshots, or billing issues. We respond within 2 hours during business hours.',
                    'partners' => 'Interested in integrating NaaraSim into your platform? Email partners via the address below — NaaraSim is a product of Supreme Ideas Agency.',
                ],
            ],
        ];
    }

    /**
     * Page content merged with admin overrides, ordered, and (for the public
     * site) filtered to visible sections.
     *
     * @return array<string, array<string, mixed>> section => fields (+ image/visible/order)
     */
    public static function page(string $page, bool $includeHidden = false): array
    {
        $defaults = self::defaults()[$page] ?? [];
        $overrides = self::overrides($page);

        $sections = [];
        $position = 0;
        foreach ($defaults as $key => $fields) {
            $over = $overrides[$key] ?? [];
            $merged = array_merge($fields, array_filter($over, fn ($v) => $v !== null && $v !== ''));
            $merged['visible'] = array_key_exists('visible', $over) ? (bool) $over['visible'] : true;
            $merged['order'] = array_key_exists('order', $over) ? (int) $over['order'] : $position;
            $merged['image'] = $over['image'] ?? '';
            $sections[$key] = $merged;
            $position++;
        }

        // Copied-in portable sections: a portable section reused on this page
        // lives only in this page's overrides (it has no default here). Rebuild
        // it from its canonical portable defaults + the stored override so it
        // renders and edits like a native section. (Reused sections feature.)
        foreach ($overrides as $key => $over) {
            if (isset($sections[$key]) || ! in_array($key, self::PORTABLE_SECTIONS, true)) {
                continue;
            }
            $base = self::portableDefaults($key);
            if ($base === []) {
                continue;
            }
            $merged = array_merge($base, array_filter($over, fn ($v) => $v !== null && $v !== ''));
            $merged['visible'] = array_key_exists('visible', $over) ? (bool) $over['visible'] : true;
            $merged['order'] = array_key_exists('order', $over) ? (int) $over['order'] : $position++;
            $merged['image'] = $over['image'] ?? '';
            $merged['_copied'] = true; // marks a reused section for the editor UI
            $sections[$key] = $merged;
        }

        uasort($sections, fn ($a, $b) => $a['order'] <=> $b['order']);

        if (! $includeHidden) {
            $sections = array_filter($sections, fn ($s) => $s['visible']);
        }

        return $sections;
    }

    /** @return array<string, array<string, mixed>> */
    public static function overrides(string $page): array
    {
        try {
            return Cache::rememberForever(self::cacheKey($page), function () use ($page) {
                $stored = Setting::getValue('site.page.'.$page, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, array<string, mixed>> $overrides */
    public static function saveOverrides(string $page, array $overrides): void
    {
        Setting::setValue('site.page.'.$page, $overrides, 'site', 'Marketing page overrides.');
        self::flush($page);
    }

    /**
     * Copy a portable section onto another page (reusable sections feature). The
     * section's current field values are snapshotted into the target page's
     * overrides under the same key, made visible, and appended to the end. Only
     * portable sections can be copied — a page-bespoke section has no shared
     * partial to render through elsewhere.
     *
     * @param  array<string, mixed>  $fields  the section's field values (no reserved keys)
     */
    public static function copySection(string $key, array $fields, string $toPage, string $image = ''): bool
    {
        if (! in_array($key, self::PORTABLE_SECTIONS, true) || ! in_array($toPage, self::PAGES, true)) {
            return false;
        }

        $overrides = self::overrides($toPage);

        // Append after everything currently on the target page.
        $maxOrder = -1;
        foreach ($overrides as $o) {
            $maxOrder = max($maxOrder, (int) ($o['order'] ?? 0));
        }
        foreach (self::page($toPage, includeHidden: true) as $s) {
            $maxOrder = max($maxOrder, (int) ($s['order'] ?? 0));
        }

        // Strip reserved/marker keys from the snapshot; store just the content.
        $clean = array_diff_key($fields, array_flip(['visible', 'order', 'image', '_copied']));

        $overrides[$key] = $clean + [
            'visible' => true,
            'order' => $maxOrder + 1,
            'image' => $image,
        ];

        self::saveOverrides($toPage, $overrides);

        return true;
    }

    public static function flush(?string $page = null): void
    {
        foreach ($page ? [$page] : self::PAGES as $p) {
            Cache::forget(self::cacheKey($p));
        }
    }

    public static function isSiteKey(string $key): bool
    {
        return str_starts_with($key, 'site.');
    }

    /**
     * Resolve a section/step image value to a servable URL. Shipped defaults are
     * relative public paths (/images/...); admin overrides are absolute Wasabi
     * (or /storage) URLs. Empty stays empty so callers can hide the slot.
     */
    public static function imageUrl(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_match('#^(https?:)?//#', $value) === 1 ? $value : asset($value);
    }

    private static function cacheKey(string $page): string
    {
        return 'site.page.'.$page;
    }
}
