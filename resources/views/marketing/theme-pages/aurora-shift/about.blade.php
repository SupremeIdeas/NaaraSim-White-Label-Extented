{{-- Per-theme custom About page — "aurora-shift" (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" fintech-terminal energy.
     Structurally distinct from every sibling About page (neon-vertex's
     split-wordmark/card-fan, midnight-signal's annotated-diagram/gradient-
     band, paperwhite's single editorial column, aries-contrast's hard-split
     ledger, noir-reserve's magazine-margin/pull-quote): a dark HERO STATEMENT
     band, a MISSION/VISION pair rendered as two "terminal readout" panels
     side by side (window-chrome cards, echoing the landing/login persona
     device), a founder section using the LAYERED GLASS PANEL WITH A SOLID
     BARRIER LAYER trick (the founder avatar stays initials-only: no photo of
     Frank is on file), and a closing hex-node operating-principles row
     connected by the same flowing current line as the landing page, for
     persona consistency. Photo: earth-space.webp (a literal "global
     network/current" visual) — its content (the connected-globe view) fits
     this persona's "continuous data flow" language better than a device or
     office shot. --}}
@once
    <style>
        @keyframes nx-aurora-current { 0% { background-position: 0 0; } 100% { background-position: 200% 0; } }
        .nx-aurora-current-line { background-image: linear-gradient(90deg, transparent 0%, rgb(var(--brand-accent)) 20%, rgb(var(--brand-primary)) 45%, transparent 55%, rgb(var(--brand-accent)) 80%, transparent 100%); background-size: 200% 100%; animation: nx-aurora-current 3.5s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .nx-aurora-current-line { animation: none; } }
    </style>
@endonce

<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.3]" aria-hidden="true"
         style="background-image: radial-gradient(rgb(var(--brand-accent) / 0.35) 1px, transparent 1px); background-size: 26px 26px;"></div>
    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-6 pb-16 pt-20 text-center sm:pt-24 lg:px-8">
        <span class="inline-flex items-center gap-1.5 rounded-[0.625rem] border border-accent/40 bg-accent/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.15em] text-accent">
            <x-icon name="trending-up" class="h-3 w-3" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-6 max-w-2xl font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

<div class="relative bg-[#F8F9FA] dark:bg-[#0c1220]">
    <svg class="absolute inset-x-0 bottom-full block h-8 w-full text-[#F8F9FA] dark:text-[#0c1220] sm:h-12" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,30 C240,58 480,2 720,26 C960,50 1200,6 1440,28 L1440,60 L0,60 Z" />
    </svg>

    {{-- Mission / Vision — two terminal-window readout panels side by side. --}}
    <section class="pb-6 pt-10 sm:pt-14">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#12172a]">
                    <div class="flex items-center gap-1.5 border-b border-slate-200 px-4 py-2.5 dark:border-white/10">
                        <span class="h-2 w-2 rounded-full bg-action/70"></span>
                        <span class="h-2 w-2 rounded-full bg-accent/70"></span>
                        <span class="h-2 w-2 rounded-full bg-primary/70"></span>
                        <span class="ml-3 font-display text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">mission.log</span>
                    </div>
                    <div class="p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-accent-dark dark:text-accent">Our mission</p>
                        <p class="mt-3 text-lg leading-relaxed text-slate-800 dark:text-slate-100">{{ $content['mission'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#12172a]">
                    <div class="flex items-center gap-1.5 border-b border-slate-200 px-4 py-2.5 dark:border-white/10">
                        <span class="h-2 w-2 rounded-full bg-action/70"></span>
                        <span class="h-2 w-2 rounded-full bg-accent/70"></span>
                        <span class="h-2 w-2 rounded-full bg-primary/70"></span>
                        <span class="ml-3 font-display text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">vision.log</span>
                    </div>
                    <div class="p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-accent-dark dark:text-accent">Our vision</p>
                        <p class="mt-3 text-lg leading-relaxed text-slate-800 dark:text-slate-100">{{ $content['vision'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Founder — layered glass panel with a solid barrier layer, over the
         earth-space photo (the same "global network/current" visual used on
         the landing page's registry default). --}}
    <section class="py-10 sm:py-14">
        <div class="mx-auto max-w-4xl px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-[1.5rem]">
                <img src="{{ asset('images/themes/shared/earth-space.webp') }}" alt=""
                     class="absolute inset-0 h-full w-full object-cover">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/90 via-navy/70 to-navy/90"></div>

                <div class="relative p-8 sm:p-12">
                    <div class="mx-auto max-w-lg rounded-[1.5rem] border border-white/15 bg-white/10 p-1.5 shadow-2xl backdrop-blur-md">
                        <div class="rounded-[1.25rem] bg-navy/95 p-6 sm:p-8">
                            <span class="flex h-12 w-12 items-center justify-center rounded-[0.625rem] border border-accent/40 font-display text-base text-accent">
                                {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                            </span>
                            <p class="mt-5 text-sm leading-relaxed text-white/85">{{ $content['founder_bio'] }}</p>
                            <p class="mt-5 font-display text-sm font-semibold text-white">{{ $content['founder_name'] }}</p>
                            <p class="text-xs text-white/60">{{ $content['founder_title'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Closing operating principles — hex nodes connected by the flowing
         current line, matching the landing page's own feature treatment. --}}
    <section class="pb-16 pt-4 sm:pb-20">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="relative grid gap-10 sm:grid-cols-3">
                <div class="pointer-events-none absolute left-[16.5%] right-[16.5%] top-6 hidden h-[2px] nx-aurora-current-line sm:block" aria-hidden="true"></div>
                @foreach ([
                    ['icon' => 'shield-check', 'title' => 'Transparent, always', 'body' => 'The rate you see is the rate you\'re charged — no hidden roaming fees, no fine print.'],
                    ['icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended to 190+ markets on the same rate card.'],
                    ['icon' => 'trending-up', 'title' => 'Priced like capital', 'body' => 'Every rate is checked and published before it ever reaches you — never marked up in the dark.'],
                ] as $card)
                    <div class="relative text-center">
                        <span class="relative z-10 mx-auto flex h-12 w-12 items-center justify-center bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-primary/30 [clip-path:polygon(50%_2%,95%_26%,95%_74%,50%_98%,5%_74%,5%_26%)]">
                            <x-icon :name="$card['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-display text-base font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                        <p class="mx-auto mt-1.5 max-w-xs text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>

@include('marketing._reused-sections')
