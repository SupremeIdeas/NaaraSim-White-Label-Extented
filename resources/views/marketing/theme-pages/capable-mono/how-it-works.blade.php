{{-- Per-theme custom How It Works page — "capable-mono" (Theme visual
     rebuild, owner request 2026-09-07). Structurally the opposite of
     paperwhite's plain serif-italic numeral list and neon-vertex's receding
     3D card row: a single bordered CONSOLE LOG card — each step rendered as
     a timestamped terminal log line (monospace "[00:0n] STEP_0n"), divided
     by hairlines, echoing the same terminal-window idiom used on the login
     screen and the landing page's capability block for suite-wide
     consistency. The device-compatibility check renders as a plain log line
     too, ending in a single lime "OK" status — the one accent on the page. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-2xl px-6 pb-10 pt-24 text-center sm:pt-28">
        <h1 class="mx-auto max-w-lg font-display text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-md text-base leading-relaxed text-slate-500 dark:text-white/45">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

@php
    $steps = [
        ['t' => '00:01', 'n' => '01', 'title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
        ['t' => '00:02', 'n' => '02', 'title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money.'],
        ['t' => '00:03', 'n' => '03', 'title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
        ['t' => '00:04', 'n' => '04', 'title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online.'],
    ];
@endphp

<section class="mx-auto max-w-2xl px-6 pb-16">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-primary">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-[#F1F2F3] px-5 py-2.5 dark:border-white/10 dark:bg-white/[0.03]">
            <span class="flex items-center gap-1.5" aria-hidden="true">
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
                <span class="h-2 w-2 rounded-sm border border-slate-400 dark:border-white/25"></span>
            </span>
            <span class="font-mono text-[11px] text-slate-400 dark:text-white/35">~/naarasim/onboarding.log</span>
        </div>

        @foreach ($steps as $step)
            <div class="flex items-start gap-4 border-t border-slate-200 px-5 py-6 first:border-t-0 dark:border-white/10 sm:px-7">
                <span class="shrink-0 font-mono text-xs text-slate-400 dark:text-white/30">[{{ $step['t'] }}]</span>
                <div class="min-w-0">
                    <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-white/30">Step_{{ $step['n'] }}</p>
                    <h3 class="mt-1.5 font-display text-lg font-semibold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-white/45">{{ $step['body'] }}</p>
                </div>
            </div>
        @endforeach

        {{-- Compatibility check — one more log line, ending in the page's
             single lime status flag. --}}
        <div class="flex items-start justify-between gap-4 border-t border-slate-200 px-5 py-6 dark:border-white/10 sm:px-7">
            <div class="min-w-0">
                <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-white/30">Check_device</p>
                <h3 class="mt-1.5 font-display text-lg font-semibold text-slate-900 dark:text-white">Check your phone supports eSIM first</h3>
                <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-white/45">
                    Most phones from the last four years do — we check automatically before you pay, so there's never a wasted purchase.
                </p>
                <p class="mt-3 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm font-medium text-slate-700 dark:text-white/70">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-white/30 dark:hover:border-white">iPhone</a>
                    <span aria-hidden="true" class="text-slate-300 dark:text-white/20">&middot;</span>
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-white/30 dark:hover:border-white">Android</a>
                    <span aria-hidden="true" class="text-slate-300 dark:text-white/20">&middot;</span>
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-white/30 dark:hover:border-white">Check mine</a>
                </p>
            </div>
            <span class="mt-1 inline-flex shrink-0 items-center gap-1.5 rounded-md border border-accent/40 px-2 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-accent">
                <x-icon name="check" class="h-3 w-3" /> OK
            </span>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
