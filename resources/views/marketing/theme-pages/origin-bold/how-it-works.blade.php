{{-- Per-theme custom How It Works page — "origin-bold" (Theme visual
     rebuild, owner request 2026-09-07). Structurally distinct from
     neon-vertex's 3D-perspective receding card row and midnight-signal's
     editorial text-column + floating-pill-rail: each step here is a big
     SOLID-COLOUR NUMERAL TILE in a horizontal strip — the numeral itself
     IS the visual, not a small icon in a corner of a white card. Tiles
     alternate primary/navy fill and butt against each other with thick
     dividers, reading as one continuous colour-block bar (stacks to a
     single column on mobile, dividers becoming full-width top borders).
     Compatibility keeps this persona's established language: square
     (not circular) icon buttons, matching the bottom-nav's own square
     centre button. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-5xl font-black uppercase leading-[0.95] text-navy dark:text-white sm:text-6xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '1', 'title' => 'Pick your destination', 'body' => 'Search 190+ countries and see live plans instantly — no account needed to browse.', 'fill' => 'bg-primary text-white'],
        ['n' => '2', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — card, bank transfer, or mobile money.', 'fill' => 'bg-navy text-white'],
        ['n' => '3', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app.', 'fill' => 'bg-primary text-white'],
        ['n' => '4', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online.', 'fill' => 'bg-navy text-white'],
    ];
@endphp

{{-- The 4 steps — solid-colour numeral tiles, the numeral IS the visual. --}}
<section class="border-y-4 border-navy dark:border-white/20">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($steps as $i => $step)
            <div class="{{ $step['fill'] }} {{ $i > 0 ? 'border-navy border-t-4 dark:border-white/20 sm:border-l-4 sm:border-t-0' : '' }} flex min-h-[17rem] flex-col justify-between p-8">
                <span class="font-display text-7xl font-black leading-none text-white/90">{{ $step['n'] }}</span>
                <div class="mt-8">
                    <h3 class="font-display text-lg font-black uppercase leading-tight">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/85">{{ $step['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Compatibility — square quick-link buttons, not circles. --}}
<section class="mx-auto max-w-2xl px-4 py-24 text-center">
    <h2 class="font-display text-2xl font-black uppercase text-navy dark:text-white">Check your phone supports eSIM first</h2>
    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last 4 years do — we check automatically before you pay, so there is never a wasted purchase.</p>

    <div class="mt-9 flex flex-wrap justify-center gap-5">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2.5">
                <span class="flex h-16 w-16 items-center justify-center border-4 border-navy bg-white text-navy transition group-hover:border-primary group-hover:bg-primary group-hover:text-white dark:border-white/20 dark:bg-white/5 dark:text-white">
                    <x-icon :name="$btn['icon']" class="h-6 w-6" />
                </span>
                <span class="text-xs font-black uppercase tracking-wide text-slate-700 dark:text-slate-300">{{ $btn['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
