{{-- Per-theme custom How It Works page — "fintra-clean" ("Ledger" persona,
     Theme visual rebuild, 2026-09-07). Structurally different from
     aries-contrast's 4-quadrant hard-edge grid and every other theme's
     card row / pill stack: a TRANSACTION-HISTORY TIMELINE — a vertical
     connecting rule between numbered, lightly-rounded badges, each step
     rendered as its own bordered "entry" card with a "Step NN" reference
     tag, reading like a statement's chronological line items rather than
     a grid of steps. Same 4 steps, same functional meaning, reworded to
     this persona's reconciliation language. --}}
@php
    $steps = [
        ['n' => '01', 'title' => 'Log your destination', 'body' => 'Search any of 190+ countries and see the live, verifiable rate in seconds — no account required to browse.'],
        ['n' => '02', 'title' => 'Post the charge, once', 'body' => 'Your wallet debits the exact rate shown — nothing added at checkout, nothing added later.'],
        ['n' => '03', 'title' => 'Scan to close the entry', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['n' => '04', 'title' => 'Land already reconciled', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online, already on the books.'],
    ];
@endphp

<section class="border-b border-slate-100 bg-white dark:border-white/5 dark:bg-navy">
    <div class="mx-auto max-w-3xl px-5 py-16 text-center sm:px-6 sm:py-20">
        <h1 class="font-display text-4xl font-bold text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-6">
        <div class="relative">
            @foreach ($steps as $step)
                <div class="relative flex gap-5 pb-10 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute bottom-0 left-[19px] top-10 w-px bg-slate-200 dark:bg-white/10" aria-hidden="true"></span>
                    @endunless
                    <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-navy font-mono text-sm font-bold text-accent">
                        {{ $step['n'] }}
                    </span>
                    <div class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-navy">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-display text-base font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                            <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-600">Step {{ $step['n'] }}</span>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Compatibility — lightly-rounded control-radius squares (never circles),
     matching this persona's near-sharp radius tokens. --}}
<section class="border-t border-slate-100 bg-white py-16 text-center dark:border-white/5 dark:bg-navy sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-6">
        <h2 class="font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted line item.</p>

        <div class="mt-8 flex justify-center gap-6">
            @foreach ([
                ['icon' => 'smartphone', 'label' => 'iPhone'],
                ['icon' => 'smartphone', 'label' => 'Android'],
                ['icon' => 'badge-check', 'label' => 'Check mine'],
            ] as $btn)
                <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="group flex flex-col items-center gap-2">
                    <span class="flex h-14 w-14 items-center justify-center rounded-md border border-slate-200 text-primary transition group-hover:border-primary group-hover:bg-primary group-hover:text-white dark:border-white/10 dark:text-slate-200">
                        <x-icon :name="$btn['icon']" class="h-6 w-6" />
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">{{ $btn['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@include('marketing._reused-sections')
