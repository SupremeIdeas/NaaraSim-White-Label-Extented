<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — How It Works'">
    {{-- Per-theme custom How It Works page (owner request, 2026-09-07): a theme
         with its own hand-built layout takes over completely, before the
         Section Builder / SiteContent flow below ever runs. --}}
    @php($themeHowStyle = \App\Support\ThemePreset::sectionStyle('how_it_works_page'))
    @if ($themeHowStyle !== 'default' && \App\Support\ThemePageLibrary::has('how_it_works_page', $themeHowStyle))
        @include(\App\Support\ThemePageLibrary::bladeFor('how_it_works_page', $themeHowStyle), ['content' => \App\Support\ThemePreset::pageContent('how_it_works_page')])
    @else
    {{-- Section Builder output wins when published; else the existing content (BUILD-6 §B). --}}
    @php($builtSections = \App\Support\PageSections::live('how-it-works'))
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @php($hero = $sections['hero'] ?? null)
    @if ($hero)
        <section class="relative overflow-hidden">
            @if (empty($hero['image']))
                {{-- Branded WebGL "connected city" backdrop (lazy, bundled,
                     CSP-safe, self-pausing off-screen). Glow orbs are the
                     reduced-motion / no-WebGL fallback. --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <span class="absolute bottom-0 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl dark:bg-primary/25"></span>
                    <span class="absolute right-1/4 top-1/3 h-40 w-40 rounded-full bg-accent/20 blur-3xl"></span>
                    <canvas data-webgl-hero="city"
                            class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
                    <div class="absolute inset-0 bg-gradient-to-b from-[#F8F9FA] via-[#F8F9FA]/45 to-[#F8F9FA] dark:from-[#0D1B2A] dark:via-[#0D1B2A]/20 dark:to-[#0D1B2A]"></div>
                </div>
            @endif
            <div class="relative z-10 mx-auto max-w-4xl px-4 pb-16 pt-20 text-center sm:pt-24">
                <h1 data-reveal class="text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">{{ $hero['headline'] }}</h1>
                <p data-reveal class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-300">{{ $hero['subtext'] }}</p>
                <p data-reveal class="mt-6 text-xs font-medium text-slate-400 dark:text-slate-400">{{ $hero['proof'] }}</p>
            </div>
        </section>
    @endif

    @php($steps = $sections['steps'] ?? null)
    @if ($steps)
        <section class="mx-auto max-w-3xl px-4 py-12">
            <h2 data-reveal class="text-center text-2xl font-bold text-slate-900 dark:text-white">{{ $steps['headline'] }}</h2>
            {{-- Trendy timeline (Module 27.5): a muted rail with a teal progress
                 line GSAP draws downward as the visitor scrolls the steps. --}}
            <ol data-timeline class="relative mt-10 space-y-8 pl-8">
                <span class="absolute bottom-1 left-[0.9rem] top-1 w-0.5 rounded-full bg-slate-200 dark:bg-[#2D4060]" aria-hidden="true"></span>
                <span data-timeline-rail class="absolute bottom-1 left-[0.9rem] top-1 w-0.5 origin-top rounded-full bg-primary" aria-hidden="true"></span>
                @foreach (range(1, 7) as $n)
                    <li data-reveal style="--reveal-delay: {{ ($n - 1) * 0.05 }}s" class="relative">
                        <span class="absolute -left-[2.1rem] top-0 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white ring-4 ring-white dark:ring-navy">{{ $n }}</span>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $steps["s{$n}_title"] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $steps["s{$n}_text"] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @php($compat = $sections['compatibility'] ?? null)
    @if ($compat)
        <section id="compatibility" class="bg-navy px-4 py-20 text-slate-100">
            <div class="mx-auto max-w-3xl text-center">
                <h2 data-reveal class="text-3xl font-bold">{{ $compat['headline'] }}</h2>
                <p data-reveal class="mt-4 leading-relaxed opacity-90">{{ $compat['intro'] }}</p>
                <p data-reveal class="mt-6 rounded-2xl border border-white/15 bg-white/5 p-4 text-sm font-medium text-accent backdrop-blur">{{ $compat['devices'] }}</p>
                <div class="mt-6 grid gap-3 text-left text-sm sm:grid-cols-2">
                    <div data-reveal class="rounded-2xl border border-white/15 bg-white/5 p-4 leading-relaxed backdrop-blur">{{ $compat['check_ios'] }}</div>
                    <div data-reveal class="rounded-2xl border border-white/15 bg-white/5 p-4 leading-relaxed backdrop-blur">{{ $compat['check_android'] }}</div>
                </div>
                <p data-reveal class="mt-6 text-sm leading-relaxed opacity-80">{{ $compat['note'] }}</p>
                <a data-reveal href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--gold mt-8 !px-8 !py-3">{{ $compat['cta'] }}</a>
            </div>
        </section>
    @endif

    @php($support = $sections['support'] ?? null)
    @if ($support)
        <section class="mx-auto max-w-3xl px-4 py-20 text-center">
            <h2 data-reveal class="text-3xl font-bold text-slate-900 dark:text-white">{{ $support['headline'] }}</h2>
            <p data-reveal class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $support['text'] }}</p>
            <blockquote data-reveal class="mx-auto mt-8 max-w-xl rounded-2xl bg-primary/10 p-5 text-sm font-medium italic text-primary dark:bg-primary/20 dark:text-teal-300">
                {{ $support['promise'] }}
            </blockquote>
            <a data-reveal href="{{ route('contact') }}" class="nx-btn nx-btn--primary mt-8 !px-8 !py-3">Contact Support</a>
        </section>
    @endif
    @include('marketing._reused-sections')
    @endif
    @endif
</x-layouts.marketing>
