{{-- Per-theme custom landing page — "midnight-signal" (Theme visual
     rebuild, owner request 2026-09-07). Structurally mimics an AI-travel
     product hero (dark gradient hero, floating data-readout cards either
     side of a central visual, a search-style CTA, then a light feature
     grid below) — recoloured in the midnight-signal cyan/near-black
     persona and rewritten for NaaraSim, reusing the same "HUD radar ring"
     motif as this theme's login screen for visual consistency across the
     whole persona. Every text/image field comes from $content (see
     LandingHeroLibrary + ThemePreset::landingContent()). Fully responsive:
     floating cards drop below the visual on mobile instead of overlapping. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'md') {
        'none' => 'rounded-none', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-2xl',
    };
@endphp
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[36rem] w-[36rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/25 blur-3xl"></span>
    </div>

    <div class="relative mx-auto max-w-5xl px-4 pb-14 pt-16 text-center sm:pb-20 sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary">
            <x-icon name="signal" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>

        <h1 class="mx-auto mt-5 max-w-2xl font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['description'] }}
        </p>

        <div class="mt-8 flex justify-center">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
               class="inline-flex items-center gap-2 rounded-full bg-primary px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/40 transition hover:bg-primary-dark">
                <x-icon name="search" class="h-4 w-4" /> {{ $content['cta_label'] }}
            </a>
        </div>

        {{-- Central visual + floating data-readout cards (stack under it on mobile). --}}
        <div class="relative mx-auto mt-14 max-w-md sm:mt-20">
            <div class="relative">
                @if ($content['image'])
                    <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover shadow-2xl">
                @else
                    <div class="relative flex aspect-[4/3] w-full items-center justify-center {{ $radiusClass }} border border-primary/20 bg-navy/60" aria-hidden="true">
                        <span class="absolute h-32 w-32 rounded-full border border-primary/25"></span>
                        <span class="absolute h-48 w-48 rounded-full border border-primary/15"></span>
                        <span class="absolute h-64 w-64 animate-ping rounded-full border border-primary/10" style="animation-duration:3s"></span>
                        <span class="relative flex h-3 w-3 rounded-full bg-primary shadow-lg shadow-primary/70"></span>
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:absolute sm:-left-16 sm:top-6 sm:mt-0 sm:w-44">
                <div class="rounded-2xl border border-primary/20 bg-navy/90 p-3 text-left shadow-xl backdrop-blur">
                    <p class="text-[10px] uppercase tracking-wide text-primary">{{ $content['stat_label'] }}</p>
                    <p class="mt-1 font-display text-xl font-bold text-white">{{ $content['stat_value'] }}</p>
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-3 sm:absolute sm:-right-16 sm:bottom-6 sm:mt-0 sm:w-44">
                <div class="rounded-2xl border border-primary/20 bg-navy/90 p-3 text-left shadow-xl backdrop-blur">
                    <p class="text-[10px] uppercase tracking-wide text-primary">Best plan found</p>
                    <p class="mt-1 font-display text-lg font-bold text-white">from $4.50</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Light feature grid — structural echo of the reference's 2x4 icon grid. --}}
<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-5xl px-4">
        <h2 class="text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">Smart features, travel effortless</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['icon' => 'globe', 'title' => 'Real-time coverage check', 'body' => 'See exactly which network you\'ll land on, before you buy.'],
                ['icon' => 'zap', 'title' => 'Instant activation', 'body' => 'Scan the QR the moment you touch down — no waiting.'],
                ['icon' => 'wallet', 'title' => 'One wallet, no surprises', 'body' => 'Data and numbers both draw from the same balance.'],
                ['icon' => 'shield-check', 'title' => 'Secure checkout', 'body' => 'Every purchase is encrypted, every wallet balance protected.'],
            ] as $feature)
                <div class="rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                        <x-icon :name="$feature['icon']" class="h-4 w-4" />
                    </span>
                    <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
