<?php

namespace App\Support;

/**
 * The registry behind a theme's full page suite beyond the homepage (owner
 * request, 2026-09-07): "for each theme, will and must carry its own
 * homepage, about us page, and 3 extra important page layouts." Mirrors
 * LandingHeroLibrary's exact pattern (page => style => blade + fields) but
 * generalized across MULTIPLE pages instead of just the landing hero, so
 * adding a 4th, 5th, 6th themed page later is one more top-level key here —
 * no new PHP class, no admin-UI code change (`Admin\ThemePicker::editPage()`
 * is entirely schema-driven off this registry, same as the landing editor).
 *
 * Pricing is deliberately absent: it's a real Livewire component with live
 * pricing logic (`Admin\PricingPage`), not a content page, so forking its
 * whole layout per theme is a materially bigger, riskier change than a
 * content-page reskin — a decision, not an oversight.
 */
class ThemePageLibrary
{
    public const PAGES = ['about_page', 'how_it_works_page', 'contact_page'];

    /**
     * @return array<string, array<string, array{blade: string, fields: array<int, array<string, mixed>>}>>
     */
    public static function registry(): array
    {
        return [
            'about_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Our story'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built by travellers, for travellers'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim started with a simple frustration: landing in a new country and losing signal at exactly the wrong moment. We built the fix — one app for data and numbers, everywhere.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'To make borders irrelevant to how connected you feel — one wallet, one app, 190+ countries.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A world where the first thing that happens off a plane is never a signal search.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank founded Supreme Ideas Agency in Onitsha to close the gap he kept hitting on his own trips across Africa: data that ran out mid-journey and numbers that didn\'t survive a border crossing. NaaraSim is the fix, built for every traveller after him.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Signal, decoded'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'We read the network so you don\'t have to'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Every carrier, every country, every fine-print roaming rule — we track it all in real time so your connection just works, the moment you land.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Predictable connectivity, everywhere — no surprise bills, no dead zones, no second-guessing.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A single signal room watching every network on Earth, so your phone never has to guess.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built the first version of this "signal room" for himself, tracking carrier quality across his own routes through Africa by hand. NaaraSim turns that same discipline into a product every traveller can lean on.'],
                    ],
                ],
                // Batch 2 (2026-09-07).
                'aries-contrast' => [
                    'blade' => 'marketing.theme-pages.aries-contrast.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'THE RECORD'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built on hard numbers, not hype.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'No soft-pedalling, no "up to" pricing. NaaraSim exists because roaming has always hidden its real cost — we put every number on the record instead.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Every price shown is the price charged — no fine print, no surprise line items, no exceptions.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A connectivity market you can verify yourself, not one you\'re asked to trust.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank got tired of roaming bills that never matched what he was quoted. NaaraSim is the correction: every rate locked and shown before you pay, every time.'],
                    ],
                ],
                'paperwhite' => [
                    'blade' => 'marketing.theme-pages.paperwhite.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Our story'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built quietly, on purpose.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'We didn\'t set out to make travel connectivity loud. We set out to make it disappear — one wallet, one app, nothing left to think about.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'To make the moment you land the least eventful part of your trip.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'Connectivity so quiet you forget it\'s there — until you need it.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built NaaraSim after one too many trips spent fighting with a local SIM counter. The goal was never more features — it was less friction.'],
                    ],
                ],
                'origin-bold' => [
                    'blade' => 'marketing.theme-pages.origin-bold.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'NO SMALL PLANS'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'We build loud, so you can travel free.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim was built to be impossible to miss — one wallet, one app, data and numbers across 190+ countries, stated plainly.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Make the biggest, boldest connectivity platform on the continent — and price it fairly, every time.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A single, unmistakable app every African traveller reaches for first.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank founded Supreme Ideas Agency in Onitsha with one bold bet: that African travellers deserved a platform built at the same scale as the trips they take.'],
                    ],
                ],
                'solar-flare' => [
                    'blade' => 'marketing.theme-pages.solar-flare.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'ON THE RECORD'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built for travellers who don\'t wait.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Every second between landing and connecting is a second lost. NaaraSim exists to close that gap to zero, every trip, every country.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Real-time coverage, real-time pricing — no waiting for a signal to catch up to you.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A connectivity platform that moves as fast as the traveller using it.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built NaaraSim to close the gap between touchdown and being online — because for a travelling founder, every minute offline is a minute of momentum lost.'],
                    ],
                ],
                'noir-reserve' => [
                    'blade' => 'marketing.theme-pages.noir-reserve.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Quiet, by design'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built for those who prefer not to be told twice.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim was built for travellers who expect things to simply work — one wallet, one number, one quiet standard of reliability.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'To be the connectivity a discerning traveller never has to think twice about.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'Understated reliability, extended to 190+ countries.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built NaaraSim on a simple standard: the best connectivity is the kind you never have to mention. Quiet, consistent, and always there.'],
                    ],
                ],
                // Batch 3 (2026-09-07): 5 brand-new personas, built from scratch.
                'aurora-shift' => [
                    'blade' => 'marketing.theme-pages.aurora-shift.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'THE THESIS'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connectivity, underwritten like capital.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Roaming has always been priced like a black box. NaaraSim applies the same rate discipline a trading desk expects to data and numbers — one live rate, published, held.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Price connectivity with the same discipline as capital markets — transparent, live, never marked up in the dark.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A single rate card for the whole continent\'s connectivity, updated in real time, trusted like a ticker.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank spent years watching roaming margins hide behind vague "up to" pricing. NaaraSim is the rate card he wished existed — live, published, and held to.'],
                    ],
                ],
                'sunset-transit' => [
                    'blade' => 'marketing.theme-pages.sunset-transit.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'FLIGHT LOG'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built at the gate, for the gate.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim started on a departures board — watching travellers scramble for a local SIM with ten minutes to boarding. We built the version that\'s already ready before you land.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Make the layover-to-landing gap the most boring part of any trip — connectivity that\'s simply already there.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'Every traveller touching down already connected, the way a ticket is already confirmed before you reach the gate.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank missed one too many gate announcements hunting for a SIM kiosk. NaaraSim is the fix: connectivity cleared for departure before you\'ve even packed.'],
                    ],
                ],
                'fintra-clean' => [
                    'blade' => 'marketing.theme-pages.fintra-clean.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'THE LEDGER'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Reconciled before you even ask.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Roaming bills read like a bank statement nobody double-checks. NaaraSim keeps one clean ledger for data and numbers — every entry matching the quote, every time.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'A ledger any accountant would sign off on — no rounding tricks, no unexplained line items.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'Connectivity billing so clean it never needs a dispute.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank reconciled one too many roaming invoices that didn\'t match the quote. NaaraSim keeps the books clean from the first charge.'],
                    ],
                ],
                'capable-mono' => [
                    'blade' => 'marketing.theme-pages.capable-mono.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'THE BUILD'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Restrained by design, capable on demand.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Most connectivity apps shout for attention. NaaraSim was built to stay out of the way — until the exact second you need data or a number, then it simply delivers.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Do the one job — data and numbers, anywhere — without asking for a second of attention it hasn\'t earned.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'An app so quiet you forget it\'s there, and so capable you never doubt it.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank wanted one app that just worked, without a dashboard demanding attention. NaaraSim is built to disappear into the background — capable the instant it\'s needed.'],
                    ],
                ],
                'waitlisty-soft' => [
                    'blade' => 'marketing.theme-pages.waitlisty-soft.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Nice to meet you'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'We just want travel to feel easier.'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim started as a favour for a friend stuck without data on a layover. It grew into an app for every traveller who\'d rather things just work — warmly, without the jargon.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Make staying connected feel like a small kindness, not a technical chore.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A connectivity app every traveller recommends to a friend, the way you\'d share a good find.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built NaaraSim after helping one too many friends untangle a confusing SIM situation abroad. He wanted the next favour to be an app, not a phone call.'],
                    ],
                ],
            ],
            'how_it_works_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'From download to connected in 4 steps'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'No store visit, no waiting on a courier — everything happens on your phone.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'The signal path, step by step'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'From the moment you buy to the moment you land, here\'s exactly what happens.'],
                    ],
                ],
                'aries-contrast' => [
                    'blade' => 'marketing.theme-pages.aries-contrast.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'The process. No surprises.'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Four steps, every one of them verifiable — nothing happens off the record.'],
                    ],
                ],
                'paperwhite' => [
                    'blade' => 'marketing.theme-pages.paperwhite.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'How it works'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Four steps. Nothing more to explain.'],
                    ],
                ],
                'origin-bold' => [
                    'blade' => 'marketing.theme-pages.origin-bold.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Four big steps. Zero small print.'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'From download to connected — stated plainly, delivered boldly.'],
                    ],
                ],
                'solar-flare' => [
                    'blade' => 'marketing.theme-pages.solar-flare.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'The play, step by step'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'From kickoff to touchdown — here\'s exactly how you get on the board.'],
                    ],
                ],
                'noir-reserve' => [
                    'blade' => 'marketing.theme-pages.noir-reserve.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'A quiet, considered process'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Four steps, handled discreetly, before you\'ve had time to notice them.'],
                    ],
                ],
                // Batch 3 (2026-09-07).
                'aurora-shift' => [
                    'blade' => 'marketing.theme-pages.aurora-shift.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'The rate, locked in 4 steps'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'From quote to activation, the number never moves.'],
                    ],
                ],
                'sunset-transit' => [
                    'blade' => 'marketing.theme-pages.sunset-transit.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Boarding process: 4 steps'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Cleared for departure before you reach the gate.'],
                    ],
                ],
                'fintra-clean' => [
                    'blade' => 'marketing.theme-pages.fintra-clean.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Four entries, one clean ledger'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Every step recorded, nothing reconciled after the fact.'],
                    ],
                ],
                'capable-mono' => [
                    'blade' => 'marketing.theme-pages.capable-mono.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Four steps. Then it just works.'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'No dashboard to babysit — set it once, forget it\'s there.'],
                    ],
                ],
                'waitlisty-soft' => [
                    'blade' => 'marketing.theme-pages.waitlisty-soft.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Four easy steps, promise'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'No jargon, no fine print — just you, connected.'],
                    ],
                ],
            ],
            'contact_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Let\'s get you connected'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Questions about a plan, a number, or your wallet? We reply fast.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Talk to the signal room'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Real humans, real answers — usually within the hour.'],
                    ],
                ],
                'aries-contrast' => [
                    'blade' => 'marketing.theme-pages.aries-contrast.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Put it on the record'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A question about a plan, a number, or a charge? Ask it straight — we answer the same way.'],
                    ],
                ],
                'paperwhite' => [
                    'blade' => 'marketing.theme-pages.paperwhite.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Get in touch'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A short message reaches a real person, quickly.'],
                    ],
                ],
                'origin-bold' => [
                    'blade' => 'marketing.theme-pages.origin-bold.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Say it loud, we\'ll answer fast'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Questions about a plan, a number, or your wallet? We reply fast, no hedging.'],
                    ],
                ],
                'solar-flare' => [
                    'blade' => 'marketing.theme-pages.solar-flare.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Get us on the line'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Fast answers, live — usually within the hour.'],
                    ],
                ],
                'noir-reserve' => [
                    'blade' => 'marketing.theme-pages.noir-reserve.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'A quiet word, whenever you need it'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A brief note reaches a real person — considered replies, usually within the hour.'],
                    ],
                ],
                // Batch 3 (2026-09-07).
                'aurora-shift' => [
                    'blade' => 'marketing.theme-pages.aurora-shift.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Talk to the desk'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Questions about a rate, a plan, or your wallet? A real answer, fast.'],
                    ],
                ],
                'sunset-transit' => [
                    'blade' => 'marketing.theme-pages.sunset-transit.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Reach the gate desk'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A quick note lands with a real person — usually within the hour.'],
                    ],
                ],
                'fintra-clean' => [
                    'blade' => 'marketing.theme-pages.fintra-clean.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Query the ledger'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A charge, a plan, a rate — ask, and we\'ll square it away fast.'],
                    ],
                ],
                'capable-mono' => [
                    'blade' => 'marketing.theme-pages.capable-mono.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Say what you need'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'No hold music, no runaround — a real answer, fast.'],
                    ],
                ],
                'waitlisty-soft' => [
                    'blade' => 'marketing.theme-pages.waitlisty-soft.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'We\'d love to hear from you'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'A friendly human reads every message — usually within the hour.'],
                    ],
                ],
            ],
        ];
    }

    public static function has(string $page, string $style): bool
    {
        return isset(self::registry()[$page][$style]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function fieldsFor(string $page, string $style): array
    {
        return self::registry()[$page][$style]['fields'] ?? [];
    }

    public static function bladeFor(string $page, string $style): ?string
    {
        return self::registry()[$page][$style]['blade'] ?? null;
    }

    public static function defaultsFor(string $page, string $style): array
    {
        return collect(self::fieldsFor($page, $style))->mapWithKeys(fn ($f) => [$f['key'] => $f['default'] ?? null])->all();
    }
}
