{{-- Per-theme custom How It Works page — "aurora-shift" (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" fintech-terminal energy.
     Structurally distinct from every sibling (noir-reserve's vertical
     serif-numeral timeline, aries-contrast's 2x2 hard-divided grid,
     midnight-signal's two-column pill stack, neon-vertex's snap-scroll
     rail, paperwhite's plain divide-y list, origin-bold's solid-colour
     numeral-tile strip): a HORIZONTAL PIPELINE of 4 hexagonal step-nodes
     (echoing the landing page's feature nodes and the bottom nav's centre
     button) connected by the persona's flowing current line, each with its
     own small terminal-window mini-card holding the step copy — a literal
     "process pipeline" read, not a tile grid, a pill stack, or a rail.
     Compatibility renders as outlined pill buttons matching the landing
     page's own secondary CTA treatment. --}}
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
        <h1 class="mx-auto max-w-2xl font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '01', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ markets and see the live rate in seconds — no account required to browse.'],
        ['n' => '02', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet settles it — top up with card, bank transfer, or mobile money, on your own schedule.'],
        ['n' => '03', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to chase — you touch down live, at the rate you locked in.'],
    ];
@endphp

<div class="relative bg-[#F8F9FA] dark:bg-[#0c1220]">
    <svg class="absolute inset-x-0 bottom-full block h-8 w-full text-[#F8F9FA] dark:text-[#0c1220] sm:h-12" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,30 C240,58 480,2 720,26 C960,50 1200,6 1440,28 L1440,60 L0,60 Z" />
    </svg>

    {{-- The pipeline — 4 hex step-nodes connected by the flowing current line. --}}
    <section class="pb-6 pt-10 sm:pt-14">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="relative grid gap-8 sm:grid-cols-4">
                <div class="pointer-events-none absolute left-[12%] right-[12%] top-6 hidden h-[2px] nx-aurora-current-line sm:block" aria-hidden="true"></div>
                @foreach ($steps as $step)
                    <div class="relative">
                        <span class="relative z-10 mx-auto flex h-12 w-12 items-center justify-center bg-gradient-to-br from-primary to-accent font-display text-xs font-bold text-white shadow-lg shadow-primary/30 [clip-path:polygon(50%_2%,95%_26%,95%_74%,50%_98%,5%_74%,5%_26%)]">
                            {{ $step['n'] }}
                        </span>

                        {{-- Mini terminal-window card holding the step copy. --}}
                        <div class="mt-4 overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#12172a]">
                            <div class="flex items-center gap-1.5 border-b border-slate-200 px-3.5 py-2 dark:border-white/10">
                                <span class="h-1.5 w-1.5 rounded-full bg-action/70"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-accent/70"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-primary/70"></span>
                            </div>
                            <div class="p-4">
                                <h3 class="font-display text-sm font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Compatibility — outlined pill buttons, matching the landing page's
         own secondary CTA treatment. --}}
    <section class="px-6 py-16 text-center sm:py-20">
        <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
        <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            Most phones from the last four years do — we check automatically before you pay, so the rate you lock in is never wasted.
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @foreach ([
                ['icon' => 'smartphone', 'label' => 'iPhone'],
                ['icon' => 'smartphone', 'label' => 'Android'],
                ['icon' => 'badge-check', 'label' => 'Check mine'],
            ] as $btn)
                <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-[0.625rem] border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-accent hover:text-accent-dark dark:border-white/20 dark:text-slate-200 dark:hover:border-accent dark:hover:text-accent">
                    <x-icon :name="$btn['icon']" class="h-4 w-4" /> {{ $btn['label'] }}
                </a>
            @endforeach
        </div>
    </section>
</div>

@include('marketing._reused-sections')
