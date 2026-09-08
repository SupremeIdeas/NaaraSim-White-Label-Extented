{{-- Per-theme custom How It Works page — "solar-flare" (Theme Batch 2,
     2026-09-07). Structurally distinct from neon-vertex's receding-3D-card
     row and midnight-signal's HUD console: steps render as a row of
     SKEWED PARALLELOGRAM cards (clip-path-cut, jersey-stripe motif,
     alternating amber/navy) rather than a plain numbered rail — on mobile
     the skew is dropped for a plain snap-scroll strip (a skewed card this
     narrow reads as a layout bug, not a design). Steps are hardcoded
     content, same precedent as neon-vertex's/midnight-signal's own
     hardcoded step arrays (not part of the editable schema — only
     headline/subtext come from $content). --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.10]" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 34px);"></div>
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-primary/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-black uppercase leading-[1.05] text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '01', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
        ['n' => '02', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money.'],
        ['n' => '03', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down live.'],
    ];
@endphp

{{-- Desktop: skewed parallelogram cards, alternating amber/navy, overlapping
     like a row of jersey stripes. --}}
<section class="hidden bg-[#F8F9FA] pb-24 pt-16 dark:bg-[#0c1220] lg:block">
    <div class="mx-auto flex max-w-5xl justify-center px-4">
        @foreach ($steps as $i => $step)
            <div class="relative -ml-4 w-60 shrink-0 p-6 pl-9 shadow-xl first:ml-0 [clip-path:polygon(12%_0,100%_0,88%_100%,0_100%)] {{ $i % 2 === 0 ? 'bg-navy text-white' : 'bg-gradient-to-br from-primary to-accent text-white' }}"
                 style="z-index: {{ 10 - $i }};">
                <span class="font-display text-3xl font-black {{ $i % 2 === 0 ? 'text-primary' : 'text-white/70' }}">{{ $step['n'] }}</span>
                <h3 class="mt-3 font-display text-base font-bold leading-tight">{{ $step['title'] }}</h3>
                <p class="mt-2 text-xs leading-relaxed {{ $i % 2 === 0 ? 'text-slate-300' : 'text-white/85' }}">{{ $step['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Mobile: plain snap-scroll strip, skew dropped. --}}
<section class="bg-[#F8F9FA] pb-16 pt-10 dark:bg-[#0c1220] lg:hidden">
    <div class="flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4">
        @foreach ($steps as $i => $step)
            <div class="w-64 shrink-0 snap-start border-t-4 border-accent p-5 shadow-sm {{ $i % 2 === 0 ? 'bg-navy text-white' : 'bg-gradient-to-br from-primary to-accent text-white' }}">
                <span class="font-display text-2xl font-black text-white/70">{{ $step['n'] }}</span>
                <h3 class="mt-3 font-display font-bold">{{ $step['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-white/85">{{ $step['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Compatibility — angular skewed pill buttons, not a gradient callout card. --}}
<section class="mx-auto max-w-2xl px-4 pb-24 pt-4 text-center">
    <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone is in the game first</h2>
    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last 4 years support eSIM — we check automatically before you pay, so there's never a wasted purchase.</p>

    <div class="mt-8 flex flex-wrap justify-center gap-4">
        @foreach ([
            ['icon' => 'smartphone', 'label' => 'iPhone'],
            ['icon' => 'smartphone', 'label' => 'Android'],
            ['icon' => 'badge-check', 'label' => 'Check mine'],
        ] as $btn)
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
               class="group inline-flex items-center gap-2 bg-gradient-to-r from-primary to-accent px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-accent/30 transition group-hover:opacity-90 [clip-path:polygon(8%_0,100%_0,92%_100%,0_100%)]">
                <x-icon :name="$btn['icon']" class="h-4 w-4" /> {{ $btn['label'] }}
            </a>
        @endforeach
    </div>
</section>

@include('marketing._reused-sections')
