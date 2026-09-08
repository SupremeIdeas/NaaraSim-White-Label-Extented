{{-- Per-theme custom landing page — "fintra-clean" ("Ledger" persona, Theme
     visual rebuild, 2026-09-07). Persona: "Fintech slate-blue dashboards
     with a champagne-gold action colour, dense tabular numbers — a clean
     accounting ledger / financial statement." Structurally different from
     every reference theme's hero: the feature image sits inside a
     RECEIPT-STYLE FRAMED CARD with a dashed caption rule under it holding
     the stat (a museum-label/receipt-caption composition), never a
     floating stat card (neon-vertex), a HUD radial scene (midnight-signal),
     a hard gold-framed photo (aries-contrast), a hairline photo caption
     (paperwhite), or an asymmetric photo block (noir-reserve). Below the
     hero, the three product ideas render as an actual RATE-CARD TABLE —
     numbered rows, a leading icon cell, a trailing tabular-nums price
     column, thin rules between rows — never cards, a newspaper-column
     strip, a scoreboard ticker, or a quadrant grid (all already used
     elsewhere). Every $content[key] below comes from LandingHeroLibrary's
     'fintra-clean' schema; the rate-card rows are the same kind of
     non-editable structural filler every other theme's 3-feature strip
     already uses. font-mono (Tailwind's default monospace stack) is used
     deliberately for reference codes and money figures — tabular-nums
     alignment is the whole point of this persona. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'none') {
        'md' => 'rounded-lg', 'xl' => 'rounded-xl', 'full' => 'rounded-full', default => 'rounded-none',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

<section class="bg-white dark:bg-navy">
    <div class="mx-auto max-w-6xl px-5 pb-14 pt-14 sm:px-6 sm:pb-20 sm:pt-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : '' }}">
                <span class="inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/5 px-3 py-1.5 font-mono text-[11px] font-semibold uppercase tracking-[0.15em] text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                    <x-icon name="file-text" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
                </span>

                <h1 class="mx-auto mt-6 max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl {{ $imageCentered ? '' : 'lg:mx-0' }} dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-base leading-relaxed text-slate-600 sm:text-lg {{ $imageCentered ? '' : 'lg:mx-0' }} dark:text-slate-400">
                    {{ $content['description'] }}
                </p>

                <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row {{ $imageCentered ? 'sm:justify-center' : 'lg:mx-0' }}">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-white/15 dark:text-slate-200">
                        <x-icon name="list" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            {{-- Receipt-style framed card: image on top, a dashed caption
                 rule underneath holding the stat, like a museum label. --}}
            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover">
                    @else
                        <div class="aspect-[4/3] w-full {{ $radiusClass }} bg-primary/10 dark:bg-white/5"></div>
                    @endif
                    <div class="mt-3 flex items-center justify-between gap-4 border-t border-dashed border-slate-200 pt-3 dark:border-white/10">
                        <span class="font-mono text-2xl font-bold leading-none tabular-nums text-primary dark:text-white">{{ $content['stat_value'] }}</span>
                        <span class="max-w-[11rem] text-right text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $content['stat_label'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- The rate card — three product ideas as an actual numbered TABLE, thin
     rules between rows, a trailing tabular-nums price column. Never cards,
     a newspaper-column strip, a scoreboard ticker, or a quadrant grid. --}}
<section class="border-t border-slate-100 bg-[#F8F9FA] py-16 dark:border-white/5 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-4xl px-5 sm:px-6">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-white/10">
            <h2 class="font-display text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">The rate card</h2>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">3 line items</span>
        </div>
        <div class="divide-y divide-slate-200 dark:divide-white/10">
            @foreach ([
                ['n' => '01', 'icon' => 'sim', 'title' => 'eSIM data', 'body' => '190+ countries, instant activation, no store visit.', 'value' => 'From $2.10'],
                ['n' => '02', 'icon' => 'phone', 'title' => 'Virtual numbers', 'body' => 'Voice and SMS that follow you, verified on arrival.', 'value' => 'From $1.50'],
                ['n' => '03', 'icon' => 'wallet', 'title' => 'One wallet', 'body' => 'Every charge draws from the same balance, itemised.', 'value' => 'No hidden fees'],
            ] as $row)
                <div class="grid grid-cols-[2rem_1fr_auto] items-center gap-4 py-5 sm:grid-cols-[2.5rem_2.75rem_1fr_auto]">
                    <span class="font-mono text-sm font-semibold text-slate-300 dark:text-slate-600">{{ $row['n'] }}</span>
                    <span class="hidden h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary sm:flex dark:bg-white/5 dark:text-slate-200">
                        <x-icon :name="$row['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $row['title'] }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $row['body'] }}</p>
                    </div>
                    <span class="whitespace-nowrap font-mono text-sm font-semibold tabular-nums text-primary dark:text-accent">{{ $row['value'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

@include('marketing._reused-sections')
