{{-- Per-theme custom How It Works page — "neon-vertex" (Theme visual
     rebuild, owner request 2026-09-07). REWRITTEN (owner feedback: stop
     recolouring the same vertical numbered card rail on every theme).
     Structure here is traced to two different references:
       - The 4-step flow: a receding 3D perspective row of cards (the
         AI-image-generator hero's trailing-card composition) on desktop;
         a plain horizontal snap-scroll strip on mobile, since a 3D
         perspective transform reads as broken tilt on a narrow screen.
       - Compatibility: three circular icon buttons in a row (Cosmos X's
         "Earth / Planets / Meteors" quick-link circles) instead of one
         big gradient callout card.
     Real photo in the mobile-only device card (owner rule: never an empty
     placeholder). --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-gradient-to-br from-primary/30 via-accent/25 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '01', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
        ['n' => '02', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money.'],
        ['n' => '03', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online.'],
    ];
@endphp

{{-- Desktop: receding 3D perspective row. --}}
<section class="hidden pb-24 lg:block" style="perspective: 1400px;">
    <div class="mx-auto flex max-w-4xl justify-center gap-6 px-4">
        @foreach ($steps as $i => $step)
            <div class="w-56 shrink-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-[#12172a]"
                 style="transform: rotateY({{ -10 - $i * 4 }}deg) translateZ({{ -$i * 18 }}px) scale({{ 1 - $i * 0.035 }}); transform-style: preserve-3d;">
                <span class="font-display text-2xl font-black text-primary">{{ $step['n'] }}</span>
                <h3 class="mt-3 font-display font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Mobile: plain snap-scroll strip (no 3D — it reads as broken tilt this narrow). --}}
<section class="pb-16 lg:hidden">
    <div class="flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4">
        @foreach ($steps as $step)
            <div class="w-64 shrink-0 snap-start rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                <span class="font-display text-2xl font-black text-primary">{{ $step['n'] }}</span>
                <h3 class="mt-3 font-display font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Compatibility — three circular quick-link buttons, not a gradient card. --}}
<section class="mx-auto max-w-2xl px-4 pb-24 text-center">
    <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted purchase.</p>

    <div class="mt-8 flex justify-center gap-8">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-accent/30 transition group-hover:scale-105">
                    <x-icon :name="$btn['icon']" class="h-6 w-6" />
                </span>
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $btn['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
