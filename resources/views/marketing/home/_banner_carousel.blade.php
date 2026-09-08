{{-- Homepage banner carousel (owner request, 2026-09-08) — the very last
     section on the homepage, decorative/non-CMS like _flags/_video/_story
     above. Reuses the existing storytelling carousel (real prev/next via its
     play/pause + dot nav, touch-swipe, lazy-loaded images) rather than
     building a second carousel system. Admin can turn the whole section off
     without touching code (Setting flag, matches every other section
     on/off switch in this codebase). --}}
@php
    // Setting::getValue() has no defensive fallback of its own (it's the
    // platform's central config accessor and assumes a migrated DB) — this
    // partial renders on every homepage view, including ones without
    // RefreshDatabase, so guard it the same way LinkPreviewSettings/
    // NumbersBento already do rather than let a missing/unmigrated
    // `settings` table 500 the whole homepage.
    try {
        $bannerCarouselEnabled = (bool) \App\Models\Setting::getValue('home.banner_carousel_enabled', true);
    } catch (\Throwable) {
        $bannerCarouselEnabled = true;
    }
@endphp
@if ($bannerCarouselEnabled)
    @php
        $bannerSlides = [
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
    @endphp
    <section data-reveal class="px-4 py-16 sm:py-20">
        <div class="mx-auto w-full max-w-5xl">
            <div class="mb-10 text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">More from Naara</p>
                <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Everything else you can do here</h2>
            </div>
            <x-storytelling-carousel :slides="$bannerSlides" section-key="home-banners" height="h-56 sm:h-80" />
        </div>
    </section>
@endif
