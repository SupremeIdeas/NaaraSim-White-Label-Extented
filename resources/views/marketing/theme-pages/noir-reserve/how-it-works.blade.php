{{-- Per-theme custom How It Works page — "noir-reserve" (Theme visual
     rebuild, brand-new persona, 2026-09-07). Structurally distinct from
     every sibling: neon-vertex's receding 3D perspective row, midnight-
     signal's two-column pill stack, paperwhite's plain divide-y list with
     numerals aligned flush left, aries-contrast's 4-quadrant hard-edge
     grid. Here: a genuine TIMELINE — a single thin gold-brown line running
     down an offset column, a small dot marking each step on the line, and
     a small SERIF NUMERAL sitting beside the line (never inside a bold
     circular badge, unlike every sibling theme's step treatment) — quiet,
     editorial pacing rather than a rail, grid, or card row. The margin
     gutter used on every other noir-reserve page appears here too, kept
     empty, so the timeline itself reads as the offset "narrower column"
     the persona's asymmetric layout always leaves. Compatibility renders
     as the same outlined/ghost buttons used for the landing page's own
     CTA, for persona consistency (never filled pills or circular icon
     buttons). --}}
<section class="relative overflow-hidden bg-[#F7F1EA] dark:bg-navy">
    <div class="mx-auto max-w-6xl px-6 pb-10 pt-20 sm:pt-24 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="hidden lg:col-span-1 lg:block" aria-hidden="true">
                <div class="h-full border-r border-accent/20"></div>
            </div>
            <div class="lg:col-span-7 lg:col-start-2">
                <h1 class="max-w-lg font-display text-3xl font-semibold leading-[1.2] text-[#241D1A] sm:text-4xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-4 max-w-md text-base leading-relaxed text-stone-600 dark:text-slate-300">
                    {{ $content['subtext'] }}
                </p>
            </div>
        </div>
    </div>
</section>

@php
    $steps = [
        ['n' => 'i', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
        ['n' => 'ii', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money, at your own pace.'],
        ['n' => 'iii', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR arrives instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => 'iv', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online, quietly.'],
    ];
@endphp

<section class="bg-[#F7F1EA] dark:bg-navy">
    <div class="mx-auto max-w-6xl px-6 pb-20 pt-4 lg:px-8 lg:pl-[calc(2.25rem+100%/12)]">
        <div class="relative max-w-xl pl-14 sm:pl-20">
            {{-- The thin gold-brown connecting line. --}}
            <div class="absolute bottom-2 left-6 top-2 w-px bg-gradient-to-b from-accent/60 via-accent/25 to-transparent sm:left-8" aria-hidden="true"></div>

            @foreach ($steps as $step)
                <div class="relative mb-14 last:mb-0">
                    {{-- Small serif numeral, sitting beside the line — never a bold circular badge. --}}
                    <span class="absolute -left-14 top-0 w-8 text-right font-display text-lg italic leading-none text-accent-dark sm:-left-20 sm:w-10 dark:text-accent">{{ $step['n'] }}</span>
                    {{-- Node dot, on the line itself. --}}
                    <span class="absolute left-6 top-1.5 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-accent sm:left-8" aria-hidden="true"></span>

                    <h3 class="font-display text-lg font-semibold text-[#241D1A] dark:text-white">{{ $step['title'] }}</h3>
                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-stone-500 dark:text-slate-400">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Compatibility — outlined/ghost buttons, matching the landing page's own
     understated CTA treatment; no icon circles, no gradient callout. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] border-t border-accent/15 bg-white px-6 py-16 text-center dark:bg-[#1C1512] sm:py-20">
    <h2 class="font-display text-xl font-semibold text-[#241D1A] dark:text-white">Check your phone supports eSIM first</h2>
    <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-stone-500 dark:text-slate-400">
        Most phones from the last four years do — we check automatically before you pay, so there's never a wasted purchase.
    </p>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
               class="inline-flex items-center gap-2 rounded-lg border border-[#241D1A]/70 px-5 py-2.5 text-sm font-semibold text-[#241D1A] transition hover:bg-[#241D1A] hover:text-white dark:border-white/30 dark:text-white dark:hover:bg-white/10">
                <x-icon :name="$btn['icon']" class="h-4 w-4" /> {{ $btn['label'] }}
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
