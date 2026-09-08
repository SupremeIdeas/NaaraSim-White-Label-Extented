{{-- Per-theme custom How It Works page — "sunset-transit" ("Boarding
     Pass", Theme Batch, 2026-09-07). Structurally distinct from solar-
     flare's skewed-parallelogram jersey-stripe row: a FLIGHT-PATH
     TIMELINE — four "gate" stops joined by a dashed route line with a
     plane-icon marker circle at each stop, alternating navy/coral,
     sitting on the same rounded-top cream sheet used across this theme's
     pages. Desktop renders the route horizontally; mobile becomes a
     vertical boarding checklist (not a snap-scroll strip). Steps are
     hardcoded content, same precedent as solar-flare's own hardcoded step
     array (only headline/subtext come from $content) — reworded to the
     boarding persona while keeping the same four functional stages: pick
     a destination, pay once, scan the QR, land connected. --}}
<section class="relative overflow-hidden bg-navy pb-10 pt-20 sm:pt-24">
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 text-center">
        <h1 class="mx-auto max-w-xl font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl lg:text-[2.65rem]">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['n' => '01', 'gate' => 'Check-in', 'title' => 'Choose where you\'re flying', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
        ['n' => '02', 'gate' => 'Ticketing', 'title' => 'Book a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money.'],
        ['n' => '03', 'gate' => 'Boarding', 'title' => 'Scan your boarding pass', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'gate' => 'Arrival', 'title' => 'Touch down already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you land live.'],
    ];
@endphp

{{-- Cream sheet pulled up over the hero (rule 5(a) treatment, matching the
     landing/about pages' own boundary shape). --}}
<section class="relative -mt-6 overflow-hidden rounded-t-[1.75rem] bg-[#FBF7F0] pb-24 pt-16 dark:bg-[#0F172A]">
    <div class="mx-auto max-w-6xl px-4">

        {{-- Desktop: horizontal flight-path timeline. --}}
        <div class="relative hidden lg:block">
            <div class="absolute inset-x-8 top-[2.125rem] h-px" aria-hidden="true"
                 style="background-image: repeating-linear-gradient(to right, rgb(var(--brand-primary)) 0 8px, transparent 8px 16px);"></div>
            <div class="grid grid-cols-4 gap-6">
                @foreach ($steps as $i => $step)
                    <div class="relative flex flex-col items-center text-center">
                        <span class="relative z-10 flex h-[4.25rem] w-[4.25rem] items-center justify-center rounded-full border-4 border-[#FBF7F0] text-white shadow-lg dark:border-[#0F172A] {{ $i % 2 === 0 ? 'bg-primary' : 'bg-accent' }}">
                            <x-icon name="plane" class="h-6 w-6 -rotate-45" />
                        </span>
                        <div class="relative mt-4 w-full rounded-2xl border-2 border-dashed border-primary/20 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-accent">Gate {{ $step['n'] }} &middot; {{ $step['gate'] }}</p>
                            <h3 class="mt-2 font-display text-sm font-bold leading-tight text-[#1B2A47] dark:text-white">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-xs leading-relaxed text-stone-500 dark:text-slate-400">{{ $step['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mobile: vertical boarding-checklist timeline. --}}
        <div class="space-y-5 lg:hidden">
            @foreach ($steps as $i => $step)
                <div class="relative flex gap-4">
                    <div class="flex flex-col items-center">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white {{ $i % 2 === 0 ? 'bg-primary' : 'bg-accent' }}">
                            <x-icon name="plane" class="h-5 w-5 -rotate-45" />
                        </span>
                        @unless ($loop->last)
                            <span class="mt-1 w-px flex-1" aria-hidden="true"
                                  style="background-image: repeating-linear-gradient(to bottom, rgb(var(--brand-primary)) 0 6px, transparent 6px 12px);"></span>
                        @endunless
                    </div>
                    <div class="flex-1 rounded-2xl border-2 border-dashed border-primary/20 bg-white p-5 pb-6 dark:border-white/10 dark:bg-white/5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-accent">Gate {{ $step['n'] }} &middot; {{ $step['gate'] }}</p>
                        <h3 class="mt-2 font-display text-sm font-bold text-[#1B2A47] dark:text-white">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-xs leading-relaxed text-stone-500 dark:text-slate-400">{{ $step['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Device-compat check CTA. --}}
        <div class="mx-auto mt-16 max-w-2xl text-center">
            <h2 class="font-display text-xl font-bold text-[#1B2A47] dark:text-white">Make sure your phone is cleared for boarding</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-stone-600 dark:text-slate-300">Most phones from the last 4 years support eSIM — we check automatically before you pay, so there's never a wasted fare.</p>

            <div class="mt-8 flex flex-wrap justify-center gap-4">
                @foreach ([
                    ['icon' => 'smartphone', 'label' => 'iPhone'],
                    ['icon' => 'smartphone', 'label' => 'Android'],
                    ['icon' => 'badge-check', 'label' => 'Check mine'],
                ] as $btn)
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-bold text-white shadow-lg shadow-accent/30 transition hover:bg-accent-dark">
                        <x-icon :name="$btn['icon']" class="h-4 w-4" /> {{ $btn['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
