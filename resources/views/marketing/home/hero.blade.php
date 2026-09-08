{{-- Hero (CMS: home.hero). Optional admin image becomes the backdrop. --}}
<section class="relative overflow-hidden" data-hero-sentinel>
    @if (! empty($s['image']))
        {{-- Apple-style hero media: admin-uploaded image with a slow GSAP
             parallax scale as the visitor scrolls (Module 27.5). --}}
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ $s['image'] }}" alt="" data-hero-media class="h-full w-full object-cover will-change-transform">
            <div class="absolute inset-0 bg-gradient-to-b from-navy/60 via-navy/70 to-navy/85"></div>
        </div>
    @else
        {{-- Branded WebGL "connected planet" hero backdrop (lazy-loaded, bundled
             through Vite so it's CSP-safe, and self-pausing off-screen). The
             glow orbs are the reduced-motion / no-WebGL fallback. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span class="absolute bottom-0 left-1/2 h-[34rem] w-[34rem] -translate-x-1/2 translate-y-1/4 rounded-full bg-primary/20 blur-3xl dark:bg-primary/25"></span>
            <span class="absolute right-[12%] top-[42%] h-56 w-56 rounded-full bg-accent/20 blur-3xl"></span>
            <canvas data-webgl-hero="planet"
                    class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
            {{-- Legibility scrim: clean at the top where the headline/subhead sit,
                 the planet stays vivid lower down. Matches the page bg in both
                 themes — firmer in light where the planet is a mid-tone. --}}
            <div class="absolute inset-0 bg-gradient-to-b from-[#F8F9FA] via-[#F8F9FA]/55 to-[#F8F9FA]/70 dark:from-[#0D1B2A] dark:via-[#0D1B2A]/15 dark:to-[#0D1B2A]/65"></div>
        </div>
        {{-- Flag + comms nodes over the planet: our brand's "connect across
             borders" message, above the scrim so they read clearly. --}}
        <x-flag-orbit tone="light" class="z-[1]" />
    @endif
    <div class="relative z-10 mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24 {{ ! empty($s['image']) ? 'text-white' : '' }}">
        {{-- Admin image branch sits on a dark navy scrim regardless of the
             site's own light/dark mode, so accent (not accent-dark) is the
             right pick there; the WebGL/no-image branch's scrim follows the
             site's mode, so it needs the usual light/dark pairing. --}}
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] {{ ! empty($s['image']) ? 'text-accent' : 'text-accent-dark dark:text-accent' }}">{{ $s['eyebrow'] }}</p>
        @php
            // Accent only the LAST word of the headline with the gradient — the
            // rest stays solid so it reads clean and professional.
            $hlWords = preg_split('/\s+/', trim($s['headline']));
            $hlLast = array_pop($hlWords) ?: '';
            $hlLead = implode(' ', $hlWords);
        @endphp
        <h1 data-reveal style="--reveal-delay:.08s"
            class="mx-auto mt-4 max-w-3xl text-4xl font-bold leading-tight sm:text-6xl {{ empty($s['image']) ? 'text-slate-900 dark:text-white' : 'text-white' }}">
            {{ $hlLead }}@if ($hlLead) @endif<span class="nx-gradient-text">{{ $hlLast }}</span>
        </h1>
        <p data-reveal style="--reveal-delay:.16s"
           class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed {{ empty($s['image']) ? 'text-slate-600 dark:text-slate-300' : 'text-slate-200' }}">
            {{ $s['subheadline'] }}
        </p>

        {{-- BUILD-13 (marketing): a real, visible showcase image (product /
             device mockup) directly under the description and above the CTAs.
             Distinct from the full-bleed `image` backdrop — a discrete, centered
             graphic with a capped height so it never pushes the CTAs + stat grid
             below the fold. Optional; the hero renders cleanly without it. --}}
        @php
            $showcase = \App\Support\SiteContent::imageUrl($s['showcase_image'] ?? '');
        @endphp
        @if ($showcase)
            <div data-reveal style="--reveal-delay:.20s" class="mx-auto mt-8 max-w-3xl">
                <img src="{{ $showcase }}" alt="{{ $s['headline'] }}" loading="lazy" decoding="async"
                     class="mx-auto max-h-[300px] w-auto max-w-full object-contain sm:max-h-[360px]">
            </div>
        @endif

        {{-- CTA row locked to two columns — never stacks (BUILD-13 §2.5).
             Compact padding/text at the smallest width so both buttons genuinely
             fit side by side at 375px, stepping up from sm:. --}}
        <div data-reveal style="--reveal-delay:.24s" class="mx-auto mt-8 grid max-w-md grid-cols-2 items-center gap-3">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary w-full justify-center !px-4 !py-3 !text-sm sm:!px-8 sm:!text-base">{{ $s['cta_primary'] }}</a>
            <a href="{{ route('how-it-works') }}" class="nx-btn nx-btn--ghost w-full justify-center !px-4 !py-3 !text-sm sm:!px-8 sm:!text-base">{{ $s['cta_secondary'] }}</a>
        </div>
        <p data-reveal style="--reveal-delay:.32s" class="mt-6 text-xs {{ empty($s['image']) ? 'text-slate-400' : 'text-slate-300' }}">{{ $s['social_proof'] }}</p>

        <dl data-reveal style="--reveal-delay:.4s" class="mx-auto mt-12 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach (['stat_1', 'stat_2', 'stat_3', 'stat_4'] as $stat)
                @php [$value, $label] = array_pad(explode(' ', $s[$stat], 2), 2, ''); @endphp
                @php($numeric = preg_match('/^(\d+)(\+?)$/', $value, $m))
                <div class="nx-card !p-4 text-center">
                    <dt class="sr-only">{{ $label }}</dt>
                    <dd class="font-display text-2xl font-bold text-primary dark:text-teal-300"
                        @if ($numeric) data-countup="{{ $m[1] }}" data-suffix="{{ $m[2] }}" @endif>{{ $value }}</dd>
                    <dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
