{{-- Per-theme custom How It Works page — "aries-contrast" (Theme visual
     rebuild, Batch 2, 2026-09-07). Persona: solid black/white, zero
     rounding, gold rule as the only decoration. Structurally different
     from neon-vertex's 3D-perspective card row and midnight-signal's
     two-column pill-stack layout: a 4-QUADRANT HARD-EDGE GRID — thick
     borders BETWEEN cells like a newspaper page or a scoreboard, each
     quadrant numbered like a ledger entry, never individual rounded
     cards or a numbered rail. Compatibility keeps the "no circles" rule
     consistent with this persona: square bordered buttons instead of the
     reference themes' rounded-full quick-link icons. --}}
<section class="border-b-2 border-accent bg-white dark:bg-black">
    <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:py-20">
        <h1 class="font-display text-4xl font-black uppercase leading-[0.95] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '01', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live, verifiable plans in seconds — no account required to browse.'],
        ['n' => '02', 'title' => 'Lock in the rate', 'body' => 'Your wallet pays once, at the exact price shown — nothing added at checkout, nothing added later.'],
        ['n' => '03', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online, on record.'],
    ];
@endphp

<section class="bg-white py-16 dark:bg-black sm:py-20">
    <div class="mx-auto max-w-4xl px-4">
        <div class="grid grid-cols-1 divide-y-2 divide-accent border-2 border-slate-900 sm:grid-cols-2 sm:divide-x-2 sm:divide-y-2 dark:border-white">
            @foreach ($steps as $step)
                <div class="p-6 sm:p-8">
                    <span class="font-display text-3xl font-black text-accent">{{ $step['n'] }}</span>
                    <h3 class="mt-3 font-display text-lg font-bold uppercase tracking-tight text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Compatibility — square bordered buttons, never circular in this
     persona (the header/bottom-nav already reject rounded corners
     entirely). --}}
<section class="border-t-2 border-accent bg-black py-16 text-center dark:bg-white sm:py-20">
    <div class="mx-auto max-w-2xl px-4">
        <h2 class="font-display text-xl font-bold uppercase tracking-tight text-white dark:text-black">Check your phone supports eSIM first</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-white/70 dark:text-black/60">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted purchase.</p>

        <div class="mt-8 flex justify-center gap-6">
            @foreach ([
                ['icon' => 'smartphone', 'label' => 'iPhone'],
                ['icon' => 'smartphone', 'label' => 'Android'],
                ['icon' => 'badge-check', 'label' => 'Check mine'],
            ] as $btn)
                <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2">
                    <span class="flex h-14 w-14 items-center justify-center border-2 border-accent text-accent transition group-hover:bg-accent group-hover:text-black">
                        <x-icon :name="$btn['icon']" class="h-6 w-6" />
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-white/80 dark:text-black/70">{{ $btn['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@include('marketing._reused-sections')
