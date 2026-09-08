{{-- Per-theme custom landing page — "aries-contrast" (Theme visual rebuild,
     Batch 2, 2026-09-07). Persona: "High-contrast black, white and gold,
     sharp corners — live-odds, luxury-travel energy" (see this theme's
     header/bottom-nav/login for the established visual language this page
     extends). Structurally different from neon-vertex's blob-gradient
     2-column hero and midnight-signal's dark radial HUD hero: a flat
     black/white "scoreboard" composition —
       - Hero: a bordered LIVE badge (reusing the header's pulse-dot motif),
         a plain uppercase headline (no gradient text — this persona has no
         gradients anywhere), and a hard, gold-framed feature image
         (image_radius respected, defaulting to zero) instead of a floating
         stat card overlapping it.
       - Ticker bar: the eyebrow/stat_value/stat_label fields rendered as a
         literal horizontal SCOREBOARD TICKER — fixed-width segments
         divided by thin gold vertical rules on an inverted (black-on-white
         become white-on-black) band, plus two static segments so the row
         reads as a real multi-stat ticker rather than one lonely number.
       - Feature strip: three items sharing ONE set of gold rules like
         newspaper columns, never individual boxed/shadowed cards (which is
         what both reference themes use).
     Every $content[key] below comes from LandingHeroLibrary's
     'aries-contrast' schema; nothing here is invented data, only structure
     and two static ticker segments / three static feature blurbs (the same
     kind of non-editable structural filler neon-vertex's 3 feature cards
     and midnight-signal's "Best plan found" card already use). Fully
     responsive: the ticker scrolls horizontally on narrow screens instead
     of wrapping. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'none') {
        'md' => 'rounded-2xl', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-none',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

<section class="relative border-b-2 border-accent bg-white dark:bg-black">
    <div class="relative mx-auto max-w-6xl px-4 pb-14 pt-14 sm:pb-20 sm:pt-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : '' }}">
                <span class="inline-flex items-center gap-2 border-2 border-accent px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">
                    <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-accent"></span>
                    </span>
                    {{ $content['eyebrow'] }}
                </span>

                <h1 class="mt-6 font-display text-4xl font-black uppercase leading-[0.95] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
                    {{ $content['description'] }}
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row {{ $imageCentered ? 'sm:justify-center' : '' }}">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 border-2 border-black bg-black px-6 py-3 text-sm font-bold uppercase tracking-wide text-white transition hover:bg-white hover:text-black dark:border-white dark:bg-white dark:text-black dark:hover:bg-black dark:hover:text-white">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 border-2 border-slate-900 px-6 py-3 text-sm font-bold uppercase tracking-wide text-slate-900 transition hover:border-accent hover:text-accent dark:border-white dark:text-white">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md border-4 border-accent">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover">
                    @else
                        <div class="aspect-square w-full {{ $radiusClass }} bg-black dark:bg-white"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Scoreboard ticker — the registered eyebrow/stat_value/stat_label fields
     as a literal ticker row, thin gold rules between fixed-width segments,
     on a band inverted from the surrounding sections (light hero -> dark
     ticker, dark hero -> light ticker) so it reads as a distinct strip. --}}
<section class="overflow-x-auto border-b-2 border-accent bg-black dark:bg-white">
    <div class="mx-auto flex max-w-6xl divide-x divide-accent/50">
        <div class="flex min-w-[220px] shrink-0 items-center gap-2 px-6 py-5">
            <span class="relative flex h-1.5 w-1.5 shrink-0" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
            </span>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white dark:text-black">{{ $content['eyebrow'] }}</p>
        </div>
        <div class="min-w-[200px] shrink-0 px-6 py-5">
            <p class="font-display text-2xl font-black leading-none text-accent">{{ $content['stat_value'] }}</p>
            <p class="mt-1.5 text-[11px] font-semibold uppercase tracking-widest text-white/70 dark:text-black/60">{{ $content['stat_label'] }}</p>
        </div>
        <div class="min-w-[200px] shrink-0 px-6 py-5">
            <p class="font-display text-2xl font-black leading-none text-accent">190+</p>
            <p class="mt-1.5 text-[11px] font-semibold uppercase tracking-widest text-white/70 dark:text-black/60">Markets on the board</p>
        </div>
        <div class="min-w-[200px] shrink-0 px-6 py-5">
            <p class="font-display text-2xl font-black leading-none text-accent">$0</p>
            <p class="mt-1.5 text-[11px] font-semibold uppercase tracking-widest text-white/70 dark:text-black/60">Fees hidden from you</p>
        </div>
    </div>
</section>

{{-- Feature strip — newspaper columns sharing one set of gold rules, never
     individual boxed/shadowed cards. --}}
<section class="bg-white py-16 dark:bg-black sm:py-20">
    <div class="mx-auto max-w-6xl px-4">
        <h2 class="text-center font-display text-2xl font-black uppercase tracking-tight text-slate-900 dark:text-white sm:text-3xl">What's on the board</h2>
        <div class="mt-10 grid divide-y-2 divide-accent border-y-2 border-accent sm:grid-cols-3 sm:divide-x-2 sm:divide-y-0">
            @foreach ([
                ['icon' => 'sim', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you\'re live — no store visit, no waiting on a courier.'],
                ['icon' => 'phone', 'title' => 'A real second number', 'body' => 'Voice + SMS that follows you, verified and working wherever you land.'],
                ['icon' => 'wallet', 'title' => 'One wallet, one price', 'body' => 'The price shown is the price charged — every time, no exceptions.'],
            ] as $card)
                <div class="px-6 py-8 text-center sm:text-left">
                    <span class="mx-auto flex h-10 w-10 items-center justify-center border-2 border-slate-900 text-slate-900 sm:mx-0 dark:border-white dark:text-white">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-display text-lg font-bold uppercase tracking-tight text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
