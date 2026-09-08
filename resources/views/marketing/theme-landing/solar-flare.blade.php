{{-- Per-theme custom landing page — "solar-flare" (Theme Batch 2,
     2026-09-07). Persona: "Vivid amber-orange with a fierce crimson pop on
     deep navy — sports-broadcast energy and urgency." Structurally distinct
     from neon-vertex's blob-hero-with-floating-card and midnight-signal's
     centred-radar hero: this is a live SCOREBOARD BROADCAST composition —
     a "LIVE" badge, an oversized scoreboard-style stat readout instead of a
     floating card, diagonal jersey-stripe decoration, a skewed CTA button,
     a scrolling live-ticker strip, and a row of angular (clip-path-cut)
     feature cards in place of a plain rounded grid. Every text/image field
     comes from $content, resolved+whitelisted by ThemePreset::landingContent()
     against LandingHeroLibrary's schema for this style. Fully responsive:
     single column on mobile, side-by-side from lg; the ticker strip freezes
     under prefers-reduced-motion. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'none') {
        'md' => 'rounded-2xl', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-none',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

{{-- 1. Hero — scoreboard broadcast composition. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.12]" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 34px);"></div>
    <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-primary/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-14 pt-16 sm:pb-20 sm:pt-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : 'text-center lg:text-left' }}">
                <span class="inline-flex items-center gap-1.5 border border-accent/60 bg-accent/15 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-[0.15em] text-accent [clip-path:polygon(6%_0,100%_0,94%_100%,0_100%)]">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
                    </span>
                    {{ $content['eyebrow'] }}
                </span>

                <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-black uppercase leading-[1.05] tracking-tight text-white sm:text-5xl lg:mx-0">
                    {{ $content['headline'] }}
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-lg leading-relaxed text-slate-300 lg:mx-0">
                    {{ $content['description'] }}
                </p>

                <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row lg:mx-0">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-primary to-accent px-7 py-3.5 text-sm font-extrabold uppercase tracking-wide text-white shadow-lg shadow-accent/30 transition hover:opacity-90 [clip-path:polygon(6%_0,100%_0,94%_100%,0_100%)]">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 border border-white/20 px-7 py-3.5 text-sm font-bold text-white transition hover:bg-white/5 [clip-path:polygon(6%_0,100%_0,94%_100%,0_100%)]">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover shadow-2xl">
                    @else
                        <div class="aspect-[4/5] w-full {{ $radiusClass }} bg-gradient-to-br from-primary via-accent to-navy shadow-2xl"></div>
                    @endif

                    {{-- The scoreboard readout — big number, small tracked label,
                         a scoreboard-console "chip" in place of the floating
                         rounded stat card other themes use. --}}
                    <div class="absolute -bottom-6 left-1/2 flex w-[85%] -translate-x-1/2 items-center gap-4 border-t-4 border-accent bg-navy px-5 py-4 shadow-2xl sm:w-auto">
                        <div>
                            <p class="font-display text-3xl font-black leading-none text-white sm:text-4xl">{{ $content['stat_value'] }}</p>
                            <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $content['stat_label'] }}</p>
                        </div>
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center bg-primary/15 text-primary [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]">
                            <x-icon name="signal" class="h-4 w-4" />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 2. Live ticker strip — a scrolling scoreboard marquee, pure CSS. --}}
@once
    <style>
        @keyframes nx-solar-ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .nx-solar-ticker-track { animation: nx-solar-ticker 26s linear infinite; }
        @media (prefers-reduced-motion: reduce) { .nx-solar-ticker-track { animation: none; } }
    </style>
@endonce
<section class="overflow-hidden border-y border-primary/20 bg-navy py-3" aria-label="Coverage highlights">
    <div class="flex w-max gap-10 nx-solar-ticker-track">
        @for ($i = 0; $i < 2; $i++)
            <div class="flex shrink-0 items-center gap-10 pr-10" aria-hidden="{{ $i === 1 ? 'true' : 'false' }}">
                @foreach ([
                    '190+ COUNTRIES ON THE BOARD',
                    'ESIM ACTIVATION IN UNDER 2 MINUTES',
                    'REAL NUMBERS · VOICE + SMS · 190+ COUNTRIES',
                    'ONE WALLET, ONE PRICE, ZERO ROAMING SURPRISES',
                    'LIVE SUPPORT ON THE CLOCK, 24/7',
                ] as $line)
                    <span class="flex shrink-0 items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-primary">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>{{ $line }}
                    </span>
                @endforeach
            </div>
        @endfor
    </div>
</section>

{{-- 3. Feature strip — angular clip-path-cut cards, not a plain rounded grid. --}}
<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-6xl px-4">
        <div class="grid gap-5 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'sim', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you\'re on the board — no store visit, no waiting for a courier.'],
                ['icon' => 'phone', 'title' => 'A real second number', 'body' => 'Voice + SMS that stays live wherever you land, on your schedule.'],
                ['icon' => 'wallet', 'title' => 'One wallet, one price', 'body' => 'Top up once — data and numbers both draw from the same live balance.'],
            ] as $card)
                <div class="relative overflow-hidden border border-slate-200 bg-white p-6 pt-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-[#12172a]">
                    <span class="absolute left-0 top-0 h-1.5 w-full bg-gradient-to-r from-primary to-accent" aria-hidden="true"></span>
                    <span class="flex h-10 w-10 items-center justify-center bg-gradient-to-br from-primary to-accent text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
