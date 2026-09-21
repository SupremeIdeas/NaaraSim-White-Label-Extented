<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — FAQ'">
    @php($hero = $sections['hero'] ?? null)
    @if ($hero)
        <section class="relative overflow-hidden">
            @if (empty($hero['image']))
                {{-- Same branded WebGL "liquid morphology" backdrop as the About/
                     Contact heroes when no admin-uploaded background exists yet
                     (glow orbs are the reduced-motion / no-WebGL fallback). --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <span class="absolute left-1/2 top-1/2 h-[26rem] w-[26rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/20 blur-3xl dark:bg-primary/25"></span>
                    <span class="absolute right-1/4 top-1/3 h-40 w-40 rounded-full bg-accent/20 blur-3xl"></span>
                    <canvas data-webgl-hero="liquid"
                            class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
                    <div class="absolute inset-0 bg-gradient-to-b from-[#F8F9FA] via-[#F8F9FA]/55 to-[#F8F9FA] dark:from-[#0D1B2A] dark:via-[#0D1B2A]/20 dark:to-[#0D1B2A]"></div>
                </div>
            @else
                {{-- Admin-uploaded background (Admin → Pages → FAQ → hero image). --}}
                <div class="absolute inset-0" aria-hidden="true">
                    <img src="{{ $hero['image'] }}" alt="" class="h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-b from-white/75 via-white/55 to-[#F8F9FA] dark:from-[#0D1B2A]/85 dark:via-[#0D1B2A]/65 dark:to-[#0D1B2A]"></div>
                </div>
            @endif
            <div class="relative z-10 mx-auto max-w-3xl px-4 pb-14 pt-20 text-center sm:pt-24">
                <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $hero['eyebrow'] }}</p>
                <h1 data-reveal class="mt-4 text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
                <p data-reveal class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
            </div>
        </section>
    @endif

    {{-- The exact same FAQ content and accordion as the homepage's own FAQ
         section — pulled live from App\Support\SiteContent's 'home' page
         'faq' key, the single source of truth, so editing it from Admin →
         Pages → Home keeps the homepage section and this dedicated page in
         sync automatically; there is no second copy to drift out of date. --}}
    @include('marketing.home.faq', ['s' => \App\Support\SiteContent::page('home')['faq'] ?? []])

    {{-- Template-level (not admin-CMS-text) refund note with a real link —
         same pattern as the Pricing page's own refund guarantee callout —
         so this critical trust point always renders a working link rather
         than depending on admin-edited free text, which is escaped for
         security and can never safely carry raw HTML. --}}
    <p class="mx-auto -mt-10 max-w-3xl px-4 pb-16 text-center text-sm text-slate-500 dark:text-slate-400">
        Verification numbers are covered too: no code within {{ \App\Jobs\PollSmsOtpJob::TIMEOUT_MINUTES }} minutes
        means an automatic refund — no ticket required. See our
        <a href="{{ route('refund-policy') }}" class="font-semibold text-primary hover:underline">refund &amp; reliability policy</a>.
    </p>

    @include('marketing._reused-sections')
</x-layouts.marketing>
