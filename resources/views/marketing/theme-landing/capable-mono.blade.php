{{-- Per-theme custom landing page — "capable-mono" (Theme visual rebuild,
     owner request 2026-09-07). Persona: "Near-black monochrome with a single
     neon-lime accent — restrained by day, electric by night." Layout DNA is
     adapted from the restrained monochrome utility-tool landing pattern seen
     on products like Linear/Raycast/Vercel: a quiet monospace eyebrow tag,
     an oversized tight-tracking headline with no gradient text anywhere, a
     single LIME primary action as the one loud element on the page, and a
     framed product screenshot with a plain bordered stat chip rather than a
     floating gradient card. Structurally distinct from origin-bold's solid
     colour-block strip, aries-contrast's scoreboard ticker, and every other
     theme's card-grid feature section: the supporting content below the
     hero is rendered as a single CONSOLE/TERMINAL-WINDOW block — a vertical
     stack of monochrome command-output rows inside one bordered card, ending
     in a blinking lime cursor prompt — never a grid of cards. Every text/
     image field comes from $content, resolved against LandingHeroLibrary's
     capable-mono schema. Fully responsive: the hero collapses to one column
     and the terminal block's rows stay full width at every size. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'md') {
        'none' => 'rounded-none', 'xl' => 'rounded-[2.5rem]', 'full' => 'rounded-full', default => 'rounded-2xl',
    };
    $imageOnLeft = ($content['image_position'] ?? 'right') === 'left';
    $imageCentered = ($content['image_position'] ?? 'right') === 'center';
@endphp

<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-16 sm:pb-24 sm:pt-24">
        <div class="grid items-center gap-14 lg:grid-cols-[1fr_1fr] lg:gap-16">
            <div class="{{ $imageOnLeft ? 'lg:order-2' : '' }} {{ $imageCentered ? 'text-center lg:col-span-2' : '' }}">
                <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-2.5 py-1 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:border-white/10 dark:text-white/45">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-sm bg-accent" aria-hidden="true"></span>
                    {{ $content['eyebrow'] }}
                </span>

                <h1 class="mt-6 max-w-xl font-display text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-slate-500 dark:text-white/45 {{ $imageCentered ? 'mx-auto' : '' }}">
                    {{ $content['description'] }}
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3 {{ $imageCentered ? 'justify-center' : '' }}">
                    {{-- The one loud element on this screen: a solid lime CTA. --}}
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-md bg-accent px-6 py-3 text-sm font-semibold text-black transition hover:bg-accent-dark hover:text-white">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('how-it-works') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900 dark:border-white/15 dark:text-white/70 dark:hover:border-white dark:hover:text-white">
                        <x-icon name="play" class="h-4 w-4" /> How it works
                    </a>
                </div>
            </div>

            <div class="{{ $imageOnLeft ? 'lg:order-1' : '' }} {{ $imageCentered ? 'lg:col-span-2' : '' }} relative">
                <div class="relative mx-auto max-w-md">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} border border-slate-200 object-cover dark:border-white/10">
                    @else
                        <div class="aspect-square w-full {{ $radiusClass }} border border-slate-200 bg-slate-100 dark:border-white/10 dark:bg-white/5"></div>
                    @endif

                    {{-- Plain bordered stat chip — deliberately NOT lime, since
                         the CTA already carries this screen's one accent. --}}
                    <div class="absolute -bottom-5 left-5 rounded-md border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-primary">
                        <p class="font-display text-xl font-bold leading-none text-slate-900 dark:text-white">{{ $content['stat_value'] }}</p>
                        <p class="mt-1 max-w-[9rem] text-[11px] font-medium leading-tight text-slate-500 dark:text-white/40">{{ $content['stat_label'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Console block — a single terminal-window card holding a vertical
         stack of monochrome command-output rows, never a card grid. Echoes
         the login screen's terminal chrome for suite-wide consistency. --}}
    <div class="border-t border-slate-200 bg-primary px-4 py-16 dark:border-white/10 sm:py-20">
        <div class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-black/40">
            <div class="flex items-center gap-3 border-b border-white/10 bg-white/[0.03] px-4 py-2.5">
                <span class="flex items-center gap-1.5" aria-hidden="true">
                    <span class="h-2 w-2 rounded-sm border border-white/25"></span>
                    <span class="h-2 w-2 rounded-sm border border-white/25"></span>
                    <span class="h-2 w-2 rounded-sm border border-white/25"></span>
                </span>
                <span class="font-mono text-[11px] text-white/35">~/naarasim/capabilities</span>
            </div>

            @foreach ([
                ['cmd' => 'esim --activate', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you are connected — no store visit, no fine print.'],
                ['cmd' => 'number --assign', 'title' => 'A real second number', 'body' => 'Voice and SMS that follow you, wherever the SIM tray can\'t.'],
                ['cmd' => 'wallet --status', 'title' => 'One wallet, one price', 'body' => 'Top up once — data and numbers both draw from the same balance.'],
            ] as $row)
                <div class="flex items-start gap-4 border-t border-white/10 px-5 py-5 first:border-t-0 sm:px-6">
                    <x-icon name="terminal" class="mt-0.5 h-4 w-4 shrink-0 text-white/30" />
                    <div class="min-w-0">
                        <p class="font-mono text-xs text-white/35">$ {{ $row['cmd'] }}</p>
                        <p class="mt-1.5 font-display text-base font-semibold text-white">{{ $row['title'] }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-white/45">{{ $row['body'] }}</p>
                    </div>
                </div>
            @endforeach

            {{-- Closing prompt — a single blinking lime cursor, the one
                 accent this section is allowed. --}}
            <div class="flex items-center gap-2 border-t border-white/10 px-5 py-4 font-mono text-xs text-white/30 sm:px-6">
                naarasim@build&nbsp;~&nbsp;%
                <span class="inline-block h-3.5 w-[7px] animate-pulse bg-accent" aria-hidden="true"></span>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
