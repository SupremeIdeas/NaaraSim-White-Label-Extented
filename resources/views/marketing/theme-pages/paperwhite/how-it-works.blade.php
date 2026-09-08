{{-- Per-theme custom How It Works page — "paperwhite" (Theme visual
     rebuild, owner request 2026-09-07). Structurally the opposite of
     neon-vertex's receding 3D card row and midnight-signal's floating pill
     rail: a plain vertical numbered list with large vertical whitespace
     between steps, SMALL TEXT-ONLY numerals (a quiet serif "01", not a
     circular badge) and no icons at all — pure typographic rhythm, the
     calmest step treatment on the platform by design. The device-
     compatibility check (a real product feature, S32) renders as a plain
     text line rather than icon buttons or a gradient callout, to keep the
     "minimal or no icons" rule intact even for functional content. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-2xl px-6 pb-4 pt-24 text-center sm:pt-28">
        <h1 class="mx-auto max-w-lg font-serif text-4xl italic leading-[1.15] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-md text-base leading-relaxed text-slate-500 dark:text-slate-400">
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

<section class="mx-auto max-w-xl px-6 pb-20 pt-6">
    <ol class="divide-y divide-slate-200 dark:divide-white/10">
        @foreach ($steps as $step)
            <li class="flex gap-6 py-10 first:pt-0">
                <span class="w-8 shrink-0 font-serif text-lg italic text-slate-300 dark:text-slate-600">{{ $step['n'] }}</span>
                <div>
                    <h3 class="font-serif text-xl italic text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>

{{-- Compatibility check — a plain masthead-style text line, not icon
     buttons or a gradient card, keeping this page's "no icons" discipline. --}}
<section class="border-t border-slate-200 bg-[#F8F9FA] px-6 py-16 text-center dark:border-white/10 dark:bg-navy">
    <h2 class="font-serif text-xl italic text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
    <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400">
        Most phones from the last four years do — we check automatically before you pay, so there's never a wasted purchase.
    </p>
    <p class="mt-6 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-sm font-semibold text-slate-700 dark:text-slate-200">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">iPhone</a>
        <span aria-hidden="true" class="text-slate-300 dark:text-slate-600">&middot;</span>
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">Android</a>
        <span aria-hidden="true" class="text-slate-300 dark:text-slate-600">&middot;</span>
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="border-b border-slate-400 pb-0.5 transition hover:border-slate-900 dark:border-slate-500 dark:hover:border-white">Check mine</a>
    </p>
</section>

@include('marketing._reused-sections')
