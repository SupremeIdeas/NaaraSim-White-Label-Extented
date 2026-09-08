{{-- Per-theme custom landing page — "origin-bold" (Theme visual rebuild,
     owner request 2026-09-07). Structurally distinct from neon-vertex's
     side-by-side blob-gradient hero and midnight-signal's centred HUD-radar
     hero: origin-bold opens with a solid colour-block IDENTITY STRIP (a
     full-width bar, not a soft pill badge), a giant outlined numeral
     watermark reused from the real `stat_value` field (not a hardcoded
     "190+"), a two-part hero where the feature image carries a HARD square
     stat block flush at its corner (no rotation, no shadow-softness — just
     a thick border), and closes with an alternating SOLID-FILL feature
     strip (no white rounded cards) that reads as one continuous colour-
     block bar. Every text/image field comes from $content, resolved
     against LandingHeroLibrary's origin-bold schema. Fully responsive:
     the hero collapses to one column and the feature strip stacks to one
     column with horizontal dividers becoming full-width top borders. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'none') {
        'md' => 'rounded-2xl', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-none',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

<section class="relative overflow-hidden bg-white dark:bg-navy">
    {{-- Solid colour-block identity strip — a full-width bar, never a soft
         translucent pill, matching the header's own colour-block bar. --}}
    <div class="border-b-4 border-navy bg-primary px-4 py-2.5 text-center dark:border-white/20">
        <span class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-white">
            <x-icon name="zap" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>
    </div>

    <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-14 sm:pb-28 sm:pt-20">
        {{-- Giant outlined numeral watermark, echoing the login screen's
             oversized "190+" motif — driven by the real stat value rather
             than a second hardcoded number. --}}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden lg:justify-start lg:pl-4" aria-hidden="true">
            <span class="select-none font-display text-[8rem] font-black leading-none text-navy/5 sm:text-[12rem] lg:text-[15rem] dark:text-white/5">{{ $content['stat_value'] }}</span>
        </div>

        <div class="relative grid items-center gap-14 lg:grid-cols-[1.1fr_0.9fr] lg:gap-10">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : '' }}">
                <h1 class="max-w-xl font-display text-5xl font-black uppercase leading-[0.95] tracking-tight text-navy dark:text-white sm:text-6xl {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-slate-600 dark:text-slate-300 {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['description'] }}
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4 {{ $imageCentered ? 'justify-center' : '' }}">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center gap-2 border-4 border-navy bg-primary px-7 py-3.5 text-sm font-black uppercase tracking-wide text-white transition hover:bg-primary-dark dark:border-white/20">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center gap-2 border-4 border-navy px-7 py-3.5 text-sm font-black uppercase tracking-wide text-navy transition hover:bg-navy hover:text-white dark:border-white/40 dark:text-white dark:hover:bg-white dark:hover:text-navy">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md {{ $imageCentered ? '' : '' }}">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} border-4 border-navy object-cover dark:border-white/20">
                    @else
                        <div class="aspect-square w-full {{ $radiusClass }} border-4 border-navy bg-primary dark:border-white/20"></div>
                    @endif

                    {{-- Hard square stat block, flush at the corner — no
                         rotation, no soft shadow, just a thick border. --}}
                    <div class="absolute -left-4 -top-4 border-4 border-navy bg-navy px-5 py-4 text-left dark:border-white/20 dark:bg-white sm:-left-8">
                        <p class="font-display text-2xl font-black leading-none text-white dark:text-navy">{{ $content['stat_value'] }}</p>
                        <p class="mt-1 max-w-[9rem] text-[11px] font-bold uppercase leading-tight tracking-wide text-white/70 dark:text-navy/70">{{ $content['stat_label'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alternating solid-fill feature strip — no rounded white cards, no
         icon circles: one continuous colour-block bar, divided by thick
         borders that become full-width top borders on mobile. --}}
    <div class="grid border-t-4 border-navy dark:border-white/20 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'sim', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you are connected — no store visit, no small print.', 'fill' => 'bg-primary text-white'],
            ['icon' => 'phone', 'title' => 'A real second number', 'body' => 'Voice and SMS that follow you, wherever you land.', 'fill' => 'bg-navy text-white'],
            ['icon' => 'wallet', 'title' => 'One wallet, one price', 'body' => 'Top up once — data and numbers both draw from it.', 'fill' => 'bg-primary text-white'],
        ] as $i => $card)
            <div class="{{ $card['fill'] }} {{ $i > 0 ? 'border-t-4 border-navy dark:border-white/20 sm:border-l-4 sm:border-t-0' : '' }} p-8">
                <x-icon :name="$card['icon']" class="h-8 w-8" />
                <h3 class="mt-4 font-display text-lg font-black uppercase">{{ $card['title'] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-white/85">{{ $card['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
