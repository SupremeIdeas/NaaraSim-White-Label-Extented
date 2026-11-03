<?php

namespace App\Support;

/**
 * The white label's own marketing voice: short, confident, a little cinematic. It overlays the base copy field by field (same keys, so
 * every editor, reset and override keeps working) and speaks as the licensee through BrandSettings, never as the vendor.
 */
class ForkCopy
{
    /** @return array<string, array<string, array<string, string>>> */
    public static function defaults(): array
    {
        $b = BrandSettings::name();
        $co = BrandSettings::company();

        return [
            'home' => [
                'hero' => [
                    'eyebrow' => 'Mobile data without borders',
                    'headline' => 'Touch down. Already online.',
                    'subheadline' => "Buy a data plan for the country you're heading to, scan once, and {$b} is live the second you land. No kiosk queue, no plastic SIM, no bill waiting for you at home.",
                    'cta_primary' => 'Pick a plan',
                    'cta_secondary' => 'Watch how it works',
                    'social_proof' => 'Live in 190+ countries · set up in minutes · refunds are automatic when we miss',
                    'stat_1' => '190+ Countries', 'stat_2' => '2 Min Install', 'stat_3' => '0 Plastic SIMs', 'stat_4' => '24/7 Humans',
                ],
                'ticker' => [
                    'items' => 'Instant eSIM · Verification numbers · Second lines · Gift cards · Refunds that just happen · One wallet',
                    'stat_1' => '190+ | Destinations', 'stat_2' => '2 min | To install', 'stat_3' => '100% | Automatic refunds', 'stat_4' => '24/7 | Human support',
                ],
                'capabilities' => [
                    'eyebrow' => 'What is inside',
                    'headline' => 'One platform,',
                    'headline_accent' => 'five things you actually need.',
                    'intro' => "Everything {$b} does is built around the same idea: fewer steps between you and a working connection. Pick a capability to see how it behaves.",
                    'c1_icon' => 'globe', 'c1_title' => 'Travel data', 'c1_text' => 'Local eSIM plans for 190+ countries, delivered the moment you pay.',
                    'c1_bullets' => "Country, regional and global plans\nFinal price shown before checkout\nTop up the same eSIM any time", 'c1_cta' => 'See data plans', 'c1_url' => '/catalogue', 'c1_image' => '',
                    'c2_icon' => 'message-circle', 'c2_title' => 'Verification numbers', 'c2_text' => 'Disposable numbers for sign-up codes, with an automatic refund when no code arrives.',
                    'c2_bullets' => "Codes in seconds\nHundreds of services\nRefund if nothing arrives", 'c2_cta' => 'Get a number', 'c2_url' => '/numbers', 'c2_image' => '',
                    'c3_icon' => 'phone', 'c3_title' => 'Second line', 'c3_text' => 'A permanent cloud number for calls and texts, kept apart from your personal one.',
                    'c3_bullets' => "Voice and SMS\nKeep it as long as you like\nForward to any phone", 'c3_cta' => 'Explore lines', 'c3_url' => '/numbers', 'c3_image' => '',
                    'c4_icon' => 'gift', 'c4_title' => 'Gift cards', 'c4_text' => 'Shopping, streaming, gaming and airtime for anyone, delivered as soon as you pay.',
                    'c4_bullets' => "Instant delivery\nSend by email or chat\nThousands of brands", 'c4_cta' => 'Send a gift', 'c4_url' => '/gift-cards', 'c4_image' => '',
                    'c5_icon' => 'wallet', 'c5_title' => 'One wallet', 'c5_text' => 'Top up once, spend on anything. Every movement is recorded with the balance before and after.',
                    'c5_bullets' => "Pay your way\nAutomatic refunds\nFull history", 'c5_cta' => 'Open your wallet', 'c5_url' => '/wallet', 'c5_image' => '',
                ],
                'how' => [
                    'eyebrow' => 'Three moves',
                    'headline' => 'From tap to signal',
                    'subtext' => 'The whole journey fits in the time it takes the plane to taxi.',
                    'step_1_title' => 'Choose where you are going',
                    'step_1_text' => 'Search a country or a whole region. Every plan shows its data, its days and its final price up front.',
                    'step_2_title' => 'Pay once, get a QR code',
                    'step_2_text' => 'Check out with a card, your wallet or a gift balance. Your eSIM lands in your inbox and in your account before the receipt does.',
                    'step_3_title' => 'Scan and fly',
                    'step_3_text' => "Add it to your phone in about two minutes. When you land, {$b} finds the best local network by itself.",
                    'cta' => 'Start now',
                    'step_1_tag' => 'Step one', 'step_2_tag' => 'Step two', 'step_3_tag' => 'Step three',
                    'step_1_chips' => 'Local · Regional · Global',
                    'step_2_chips' => 'Card · Wallet · Gift balance',
                    'step_3_chips' => 'About 2 minutes · No plastic',
                    'step_1_image' => '', 'step_2_image' => '', 'step_3_image' => '',
                ],
                'features' => [
                    'eyebrow' => 'Why people stay',
                    'headline' => "What makes {$b} different",
                    'f1_title' => 'Delivered before the receipt',
                    'f1_text' => 'Your eSIM is ready the moment payment clears. Nothing ships, nothing waits.',
                    'f2_title' => 'Your number stays yours',
                    'f2_text' => 'Run it next to your normal SIM. Calls and texts keep landing on the number people know.',
                    'f3_title' => 'Cover that follows the map',
                    'f3_text' => 'One country, a whole region or the world. Pick the footprint that matches the trip.',
                    'f4_title' => 'Prices you can read',
                    'f4_text' => 'The number you see is the number you pay. No roaming add-ons, no monthly leftovers.',
                    'f5_title' => 'Top up in two taps',
                    'f5_text' => 'Short on data halfway through a trip? Add more to the same eSIM. Nothing to reinstall.',
                    'f6_title' => 'Real people on support',
                    'f6_text' => 'Chat or email a human who can actually fix it, at any hour.',
                ],
                'products' => [
                    'eyebrow' => 'One account, four ways in',
                    'headline' => 'Everything in one wallet',
                    'subtext' => "{$b} Data keeps you online abroad. Numbers keep you reachable. {$b} Gift keeps you generous. One login, one balance, one place to look.",
                    'p1_title' => 'Travel data',
                    'p1_text' => 'Local speeds in 190+ countries, installed before you fly. Land and you are already connected.',
                    'p1_cta' => 'See data plans',
                    'p2_title' => 'Verification numbers',
                    'p2_text' => 'A disposable number for the codes apps send you. It arrives in seconds, and if no code comes you are refunded automatically.',
                    'p2_cta' => 'Get a number',
                    'p3_title' => 'A second line',
                    'p3_text' => 'A permanent cloud number for calls and texts. Keep business separate, keep your own number private.',
                    'p3_cta' => 'Explore lines',
                    'p4_title' => 'Gift cards',
                    'p4_text' => 'Send shopping, streaming, gaming and airtime credit to anyone, anywhere. It arrives by email or chat as soon as you pay.',
                    'p4_cta' => 'Send a gift',
                ],
                'audiences' => [
                    'eyebrow' => 'Built for movers',
                    'headline' => 'Whoever you travel as',
                    'subtext' => 'Different trips, same promise: you are online when you arrive. Pick the one that sounds like you.',
                ],
                'coverage' => [
                    'eyebrow' => 'The map',
                    'headline' => '190+ countries. One eSIM.',
                    'text' => "From the airport arrivals hall to the last mile of a road trip, {$b} connects you to a strong local network. Every continent, every major corridor, every destination worth the flight.",
                    'highlights' => 'Europe · North America · Asia Pacific · Middle East · Latin America · Africa',
                    'callout' => 'Hopping between countries? A regional plan covers the whole loop without switching.',
                    'cta' => 'Check your destination',
                ],
                'pricing' => [
                    'eyebrow' => 'Straight pricing',
                    'headline' => 'Pay for the trip, not the month',
                    'text' => 'No subscriptions, no contracts, no minimums you will forget about. Choose days and data, see the final price, done. Every plan includes instant activation and round-the-clock help.',
                    'callout' => 'From $5 · no hidden fees · nothing to cancel',
                    'cta' => 'Browse all plans',
                    'trust' => 'You only pay when you go.',
                ],
                'testimonials' => [
                    't1_name' => 'Maya R.', 't1_route' => 'Toronto → Lisbon',
                    't2_name' => 'Daniel K.', 't2_route' => 'Berlin → Bangkok',
                    't3_name' => 'Sofia L.', 't3_route' => 'Madrid → Cape Town',
                    'eyebrow' => 'In their words',
                    'headline' => 'Travellers, mid-journey',
                    't1_quote' => 'Bought the plan on the couch the night before. Landed, glanced at my phone, and it was already on a local network. I never saw the switch.',
                    't2_quote' => 'I have tried a few of these apps and none felt this finished. Coverage matched my route, and topping up took ten seconds.',
                    't3_quote' => 'No hunting for a shop, no roaming bill when I got home. The whole thing was over before I had unpacked.',
                ],
                'blogs' => [
                    'eyebrow' => 'Field notes',
                    'headline' => 'Guides for the well connected',
                    'cta_label' => 'All articles',
                    'card_cta' => 'Read',
                ],
                'faq' => [
                    'headline' => 'Quick answers',
                    'cta' => 'Still stuck? A person will answer within the hour.',
                ],
                'cta' => [
                    'headline' => 'Your next trip starts online',
                    'subtext' => "Get {$b} before you leave and spend your first hour somewhere new, not looking for signal.",
                    'cta_primary' => 'Get connected',
                    'cta_secondary' => 'See plans',
                    'trust' => 'Instant activation · no contracts · 190+ countries · real support',
                ],
            ],
            'faq' => [
                'hero' => ['eyebrow' => 'Help', 'headline' => 'Questions, answered', 'subtext' => 'eSIMs, numbers, payments and refunds in plain language.'],
            ],
            'about' => [
                'hero' => [
                    'eyebrow' => 'About us',
                    'headline' => 'We make getting online the easy part',
                    'subtext' => "{$b} began with a familiar frustration: landing somewhere new and spending the first hour fixing a phone. We thought that hour belonged to you, so we built the shortcut.",
                ],
                'story' => [
                    'headline' => 'How we got here',
                    'p1' => 'Anyone who travels knows the routine. Wheels down, phone in hand, then the search for a shop, a plan and a signal. It is slow, it is overpriced and it always happens when you are most tired.',
                    'p2' => "So we built {$b}: buy the plan from your couch, install it in minutes and arrive connected. Behind the simple screen sits a lot of careful engineering about networks, pricing and refunds, all so you never have to think about it.",
                    'p3' => 'We keep the product honest on purpose. Prices are shown before you pay. If something fails, the refund is automatic. If you need help, you reach a person.',
                    'p4' => 'We would rather be the service people quietly rely on than the loudest one in the room.',
                ],
                'mission' => [
                    'headline' => 'What drives us',
                    'mission_title' => 'Mission',
                    'mission' => 'Make staying connected anywhere in the world instant, affordable and dull in the best possible way: it just works.',
                    'vision_title' => 'Vision',
                    'vision' => 'A world where crossing a border never means losing your connection, your number or your peace of mind.',
                    'belief_title' => 'Belief',
                    'belief' => 'Connectivity is infrastructure, not a luxury. Everyone who lands somewhere new should simply be online.',
                ],
                'values' => [
                    'headline' => 'How we work',
                    'v1_title' => 'Plain language',
                    'v1_text' => 'Plans, prices and messages are written so a first-time user understands them without a glossary.',
                    'v2_title' => 'Minutes, not hours',
                    'v2_text' => 'Every step from choosing to connected is built to be quick, and we measure it.',
                    'v3_title' => 'Fair by default',
                    'v3_text' => 'Honest pricing, automatic refunds and support that treats your time as valuable.',
                    'v4_title' => 'Dependable',
                    'v4_text' => 'When a call or a payment matters, a dropped connection costs more than data. We build for the moments that count.',
                ],
                'team' => [
                    'headline' => "The people behind {$b}",
                    'founder_name' => "The {$b} team",
                    'founder_title' => $co,
                    'founder_bio' => "A small group of engineers, support specialists and travellers who use the product themselves and fix what annoys them.",
                    'company' => "{$b} is operated by {$co}.",
                ],
            ],
            'how-it-works' => [
                'hero' => [
                    'headline' => 'From checkout to connected in minutes',
                    'subtext' => 'No technical knowledge needed. Follow these steps once and it becomes a habit.',
                    'proof' => 'No shop visit · no plastic SIM · no roaming setup · works on most modern phones',
                ],
                'steps' => [
                    'headline' => 'Every step, in order',
                    's1_title' => 'Check your phone',
                    's1_text' => 'Make sure it supports eSIM and is not locked to a carrier. Most phones from 2019 onward do. The compatibility checker will tell you in seconds.',
                    's2_title' => 'Pick a plan',
                    's2_text' => 'Choose a country, a region or the whole world, then the data and days that fit the trip.',
                    's3_title' => 'Pay securely',
                    's3_text' => 'Use a card or your wallet. The QR code and instructions arrive in your inbox and your account straight away.',
                    's4_title' => 'Install it before you go',
                    's4_text' => 'Scan the code or add it from your phone settings. It takes about ninety seconds on Wi-Fi.',
                    's5_title' => 'Choose it for data',
                    's5_text' => 'Set the new eSIM as your data line and leave your usual SIM on for calls and texts.',
                    's6_title' => 'Land and relax',
                    's6_text' => 'Your phone joins a local network by itself. Switch off data roaming on your home SIM to be safe.',
                    's7_title' => 'Top up if you need',
                    's7_text' => 'Add more data to the same eSIM from your account. Nothing to reinstall.',
                ],
                'compatibility' => [
                    'headline' => 'Will it work on your phone?',
                    'intro' => 'Any eSIM-capable phone that is not carrier-locked. Here is how to tell:',
                    'devices' => 'iPhone XS and later · Samsung Galaxy S20 and later · Google Pixel 3 and later · most flagship Androids from 2020',
                    'check_ios' => 'iPhone: Settings, Cellular, Add Cellular Plan. If you see it, you are ready.',
                    'check_android' => 'Android: Settings, Network and internet, SIMs, Add SIM. Look for the eSIM option.',
                    'note' => 'Some regional models ship without eSIM hardware. Not sure? Run the checker or ask support and we will tell you before you buy.',
                    'cta' => 'Check my phone',
                ],
                'support' => [
                    'headline' => 'We are one message away',
                    'text' => 'Most installs go smoothly. When one does not, you can reach a real person who stays with you until it works.',
                    'promise' => 'A ticket is only closed when your problem is solved, not when we have replied.',
                ],
            ],
            'contact' => [
                'hero' => [
                    'headline' => 'Talk to a person',
                    'subtext' => 'A question before you buy, or a snag after? Write to us. A real team reads every message.',
                    'promise' => 'Typical reply: within the hour',
                ],
                'form' => [
                    'headline' => 'Send a message',
                    'success' => 'Got it. A teammate will reply soon. If you do not see us in your inbox, check spam.',
                    'disclaimer' => 'We never share your details.',
                ],
                'channels' => [
                    'headline' => 'More ways to reach us',
                    'chat_title' => 'Live chat',
                    'chat_text' => 'The quickest route. Our assistant answers first and hands you to a human whenever you want.',
                    'email_title' => 'Email',
                    'email_address' => BrandSettings::profile('support_email'),
                    'email_text' => 'Best for screenshots, billing questions and anything detailed.',
                    'partners' => "Want to build on {$b}? Write to us and ask for the partnerships team.",
                ],
            ],
        ];
    }
}
