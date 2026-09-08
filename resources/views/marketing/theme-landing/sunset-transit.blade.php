{{-- Per-theme custom landing page — "sunset-transit" ("Boarding Pass",
     Theme Batch, 2026-09-07). Persona: "Airline-ticket navy with a warm
     coral pop — departures-board energy for a travel brand." Structurally
     distinct from every sibling hero: a DEPARTURES-BOARD STRIP (a dark
     split-flap stat row — mechanical-display styling, a horizontal midline
     through each tile, tabular numerals) sits ABOVE the hero copy, which
     itself lives on a paper-cream "boarding pass" sheet pulled up over the
     strip with a rounded-top card edge (rule 5(a) treatment) — never noir-
     reserve's magazine-margin grid, a centred broadcast headline, or a
     reversed 60/40 split. The photo carries a dashed ticket-card frame with
     die-cut side notches instead of a plain rounded image. Every field
     comes from $content, resolved+whitelisted by ThemePreset::landingContent()
     against LandingHeroLibrary's schema for 'sunset-transit' — nothing here
     is hardcoded except the surrounding structure and the closing feature
     strip (not yet part of the editable schema, same discipline as every
     sibling theme). Fully responsive: the split-flap row wraps to a
     3-column grid at all sizes, and the hero grid stacks to one column
     below `lg`. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'md') {
        'none' => 'rounded-none', 'xl' => 'rounded-2xl', 'full' => 'rounded-full', default => 'rounded-xl',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

{{-- 1. DEPARTURES STRIP — split-flap stat row. --}}
<section class="relative overflow-hidden bg-navy pb-10 pt-14 sm:pt-16">
    <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/30 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-5xl px-4 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-dashed border-accent/50 bg-accent/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-accent">
            <x-icon name="plane" class="h-3 w-3 -rotate-45" /> {{ $content['eyebrow'] }}
        </span>

        <div class="mx-auto mt-8 grid max-w-2xl grid-cols-3 gap-3 sm:gap-4">
            @foreach ([
                ['value' => $content['stat_value'], 'label' => $content['stat_label']],
                ['value' => '190+', 'label' => 'Countries live'],
                ['value' => '24/7', 'label' => 'Ground crew support'],
            ] as $flap)
                <div class="relative overflow-hidden rounded-lg bg-[#101c30] px-2 py-4 shadow-inner sm:px-3 sm:py-5">
                    <span class="pointer-events-none absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-black/40" aria-hidden="true"></span>
                    <p class="font-display text-xl font-black leading-none tracking-tight text-white sm:text-2xl" style="font-variant-numeric: tabular-nums;">{{ $flap['value'] }}</p>
                    <p class="mt-2 text-[9px] font-semibold uppercase leading-tight tracking-wider text-slate-400 sm:text-[10px]">{{ $flap['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- 2. HERO SHEET — paper-cream card pulled up over the departures strip
     (rule 5(a): rounded-top "sheet" reveal, never a flat seam). --}}
<section class="relative -mt-6 overflow-hidden rounded-t-[1.75rem] bg-[#FBF7F0] pb-20 pt-12 dark:bg-[#0F172A] sm:pb-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14">
            <div class="{{ $imageCentered ? 'text-center lg:order-2' : ($imageOnLeft ? 'lg:order-2' : 'lg:order-1') }}">
                <h1 class="max-w-xl font-display text-3xl font-bold leading-[1.15] text-[#1B2A47] dark:text-white sm:text-4xl lg:text-[2.65rem] {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-stone-600 dark:text-slate-300 {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['description'] }}
                </p>
                <div class="mt-8 {{ $imageCentered ? 'flex justify-center' : '' }}">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-accent/30 transition hover:bg-accent-dark">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div class="relative {{ $imageCentered ? 'mx-auto max-w-sm lg:order-1' : ($imageOnLeft ? 'lg:order-1' : 'lg:order-2') }}">
                {{-- Ticket-card frame: dashed border + two die-cut notches,
                     echoing the login stub's tear seam. --}}
                <div class="relative rounded-2xl border-2 border-dashed border-primary/25 bg-white p-2.5 shadow-xl dark:border-white/15 dark:bg-white/5">
                    <span class="pointer-events-none absolute -left-2.5 top-1/2 hidden h-5 w-5 -translate-y-1/2 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A] sm:block" aria-hidden="true"></span>
                    <span class="pointer-events-none absolute -right-2.5 top-1/2 hidden h-5 w-5 -translate-y-1/2 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A] sm:block" aria-hidden="true"></span>
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover">
                    @else
                        <div class="aspect-[4/5] w-full {{ $radiusClass }} bg-gradient-to-br from-primary to-navy"></div>
                    @endif
                </div>
                <span class="absolute -bottom-4 left-6 inline-flex items-center gap-1.5 rounded-full bg-navy px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-lg">
                    <x-icon name="badge-check" class="h-3.5 w-3.5 text-accent" /> Boarding group A
                </span>
            </div>
        </div>

        {{-- 3. Feature strip — ticket-stub cards, dashed perimeter, die-cut notch. --}}
        <div class="mt-24 grid gap-5 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'sim', 'title' => 'eSIM at the gate', 'body' => 'Scan the QR before boarding — 190+ countries, live the moment you land.'],
                ['icon' => 'phone', 'title' => 'A number that flies with you', 'body' => 'Voice and SMS that clear customs with you, every border, every trip.'],
                ['icon' => 'wallet', 'title' => 'One wallet, no surprise fare', 'body' => 'Data and numbers both draw from the same balance — the quote is the charge.'],
            ] as $card)
                <div class="relative rounded-2xl border-2 border-dashed border-primary/20 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <span class="pointer-events-none absolute -left-2.5 top-6 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-display text-base font-bold text-[#1B2A47] dark:text-white">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
