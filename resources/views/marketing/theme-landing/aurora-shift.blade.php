{{-- Per-theme custom landing page — "aurora-shift" (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" — deep indigo-violet fintech
     energy with electric-blue highlights on a near-black gradient; a
     trading-terminal read on a travel-money app (Dribbble/Awwwards-style
     fintech dashboard landing patterns, adapted to NaaraSim's content).
     Structurally distinct from every sibling hero (neon-vertex's blob/
     floating-card, midnight-signal's centred radar, solar-flare's
     scoreboard broadcast, aries-contrast's flat ticker, origin-bold's
     identity-strip, paperwhite's editorial column, noir-reserve's
     magazine-margin): a literal MOCK TERMINAL WINDOW holding the feature
     image behind an app-chrome title bar, with a floating RADIAL GAUGE
     stat badge (SVG stroke, not a plain card) overlapping its corner.
     Section 2 is a GRID OF DASHBOARD METRIC TILES — each with its own
     embedded mini sparkline SVG — rather than a scrolling text marquee or
     card row. Section 3 connects 3 feature nodes with a single flowing
     "current" line through hexagonal icon badges (echoing the bottom nav's
     hex centre button), a device nobody else on the platform uses. Every
     text/image field comes from $content, resolved+whitelisted by
     ThemePreset::landingContent() against LandingHeroLibrary's schema for
     this style. Fully responsive: single column on mobile; the ticker/
     sparkline animations freeze under prefers-reduced-motion. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'md') {
        'none' => 'rounded-none', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-[1.5rem]',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp
@once
    <style>
        @keyframes nx-aurora-current { 0% { background-position: 0 0; } 100% { background-position: 200% 0; } }
        .nx-aurora-current-line { background-image: linear-gradient(90deg, transparent 0%, rgb(var(--brand-accent)) 20%, rgb(var(--brand-primary)) 45%, transparent 55%, rgb(var(--brand-accent)) 80%, transparent 100%); background-size: 200% 100%; animation: nx-aurora-current 3.5s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-current-line { animation: none; } }
        @keyframes nx-aurora-eq { 0%, 100% { transform: scaleY(0.35); } 50% { transform: scaleY(1); } }
        .nx-aurora-eq-bar { animation: nx-aurora-eq 1.1s ease-in-out infinite; transform-origin: bottom; }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-eq-bar { animation: none; transform: scaleY(0.7); } }
    </style>
@endonce

{{-- 1. Hero — mock terminal window + radial gauge stat badge. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.3]" aria-hidden="true"
         style="background-image: radial-gradient(rgb(var(--brand-accent) / 0.35) 1px, transparent 1px); background-size: 26px 26px;"></div>
    <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-accent/15 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-16 sm:pb-24 sm:pt-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : 'text-center lg:text-left' }}">
                <span class="inline-flex items-center gap-1.5 rounded-[0.625rem] border border-accent/40 bg-accent/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.15em] text-accent">
                    <span class="flex h-3 items-end gap-[2px]" aria-hidden="true">
                        <span class="nx-aurora-eq-bar h-full w-[2px] rounded-full bg-accent" style="animation-delay:-0.9s"></span>
                        <span class="nx-aurora-eq-bar h-full w-[2px] rounded-full bg-accent" style="animation-delay:-0.4s"></span>
                        <span class="nx-aurora-eq-bar h-full w-[2px] rounded-full bg-accent" style="animation-delay:-0.1s"></span>
                    </span>
                    {{ $content['eyebrow'] }}
                </span>

                <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-[1.08] tracking-tight text-white sm:text-5xl lg:mx-0">
                    {{ $content['headline'] }}
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-lg leading-relaxed text-slate-300 lg:mx-0">
                    {{ $content['description'] }}
                </p>

                <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row lg:mx-0">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-[0.625rem] bg-gradient-to-r from-primary to-accent px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/30 transition hover:opacity-90">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-[0.625rem] border border-white/15 px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-white/5">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md">
                    {{-- Mock terminal window — app chrome + the feature image as its "screen". --}}
                    <div class="overflow-hidden {{ $radiusClass }} border border-white/10 bg-navy/70 shadow-2xl backdrop-blur-md">
                        <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-2.5">
                            <span class="h-2 w-2 rounded-full bg-action/70"></span>
                            <span class="h-2 w-2 rounded-full bg-accent/70"></span>
                            <span class="h-2 w-2 rounded-full bg-primary/70"></span>
                            <span class="ml-3 truncate font-display text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">naara.app // wallet</span>
                        </div>
                        @if ($content['image'])
                            <img src="{{ $content['image'] }}" alt="" class="aspect-[4/5] w-full object-cover">
                        @else
                            <div class="aspect-[4/5] w-full bg-gradient-to-br from-primary via-accent to-navy"></div>
                        @endif
                    </div>

                    {{-- Floating radial-gauge stat badge — an SVG stroke
                         readout, not a plain card, overlapping the corner. --}}
                    <div class="absolute -bottom-6 -left-6 flex items-center gap-3 rounded-[1.5rem] border border-white/10 bg-navy p-4 shadow-2xl">
                        <svg viewBox="0 0 40 40" class="h-12 w-12 -rotate-90 shrink-0" aria-hidden="true">
                            <circle cx="20" cy="20" r="16" fill="none" stroke="rgb(255 255 255 / 0.1)" stroke-width="4" />
                            <circle cx="20" cy="20" r="16" fill="none" stroke="rgb(var(--brand-accent))" stroke-width="4"
                                    stroke-linecap="round" stroke-dasharray="100.5" stroke-dashoffset="14" pathLength="100.5" />
                        </svg>
                        <div>
                            <p class="font-display text-xl font-bold leading-none text-white">{{ $content['stat_value'] }}</p>
                            <p class="mt-1 max-w-[7.5rem] text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $content['stat_label'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Wave divider — this persona's own boundary shape, revealing the next
     section's ground colour rising into the dark hero above it. --}}
<div class="relative bg-[#F8F9FA] dark:bg-[#0c1220]">
    <svg class="absolute inset-x-0 bottom-full block h-8 w-full text-[#F8F9FA] dark:text-[#0c1220] sm:h-12" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,30 C240,58 480,2 720,26 C960,50 1200,6 1440,28 L1440,60 L0,60 Z" />
    </svg>

    {{-- 2. Live coverage board — a grid of dashboard metric TILES, each with
         its own embedded mini sparkline, not a scrolling text marquee. --}}
    <section class="pb-4 pt-10 sm:pt-14" aria-label="Live coverage board">
        <div class="mx-auto max-w-6xl px-4">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['label' => 'Countries on the rate card', 'value' => '190+', 'path' => 'M0,20 L15,14 L30,17 L45,8 L60,11 L75,4 L90,9 L100,3'],
                    ['label' => 'Median activation time', 'value' => '<2 min', 'path' => 'M0,10 L15,16 L30,9 L45,15 L60,6 L75,12 L90,5 L100,8'],
                    ['label' => 'Wallet uptime, rolling 90d', 'value' => '99.98%', 'path' => 'M0,18 L15,15 L30,16 L45,10 L60,12 L75,6 L90,7 L100,2'],
                ] as $tile)
                    <div class="relative overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#12172a]">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $tile['label'] }}</p>
                        <p class="mt-1.5 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $tile['value'] }}</p>
                        <svg viewBox="0 0 100 24" preserveAspectRatio="none" class="mt-3 h-6 w-full text-accent" aria-hidden="true">
                            <path d="{{ $tile['path'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. Three feature nodes connected by a single flowing current line
         through hexagonal icon badges — echoes the bottom nav's hex button. --}}
    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4">
            <div class="relative grid gap-10 sm:grid-cols-3">
                <div class="pointer-events-none absolute left-[16.5%] right-[16.5%] top-6 hidden h-[2px] nx-aurora-current-line sm:block" aria-hidden="true"></div>
                @foreach ([
                    ['icon' => 'sim', 'title' => 'eSIM, priced live', 'body' => 'Scan a QR and you\'re on the network — the rate you saw is the rate you paid, no markup surprises.'],
                    ['icon' => 'phone', 'title' => 'A number that holds', 'body' => 'Voice + SMS that stays live wherever you land, drawing from the same wallet as your data.'],
                    ['icon' => 'wallet', 'title' => 'One ledger, one balance', 'body' => 'Top up once — every plan and every number settles against a single, transparent balance.'],
                ] as $card)
                    <div class="relative text-center">
                        <span class="relative z-10 mx-auto flex h-12 w-12 items-center justify-center bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-primary/30 [clip-path:polygon(50%_2%,95%_26%,95%_74%,50%_98%,5%_74%,5%_26%)]">
                            <x-icon :name="$card['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                        <p class="mx-auto mt-1.5 max-w-xs text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>

@include('marketing._reused-sections')
