{{-- Per-theme custom landing page — "noir-reserve" (Theme visual rebuild,
     brand-new persona, 2026-09-07). Persona: "Espresso-brown with a
     burnt-sienna accent — quiet, tactile, old-money luxury." Structurally
     distinct from every sibling theme's hero: an ASYMMETRIC MAGAZINE-MARGIN
     composition (the pattern earmarked in PROGRESS.md) rather than
     neon-vertex's centred lg:grid-cols-2 split, midnight-signal's centred
     stack with flanking data-readout cards, or origin-bold's reversed
     60/40 columns. A 12-column grid where column 1 is a genuinely empty,
     decorative quiet margin (never used by any sibling theme's landing
     hero), the text lives in a narrower offset column next to it, and the
     photo fills the remaining, larger span — muted photography under a
     LAYERED GLASS PANEL WITH A SOLID BARRIER LAYER holding the stat
     (translucent outer card + fully solid inner card, for real contrast —
     the same trick used on this theme's login screen) rather than a
     floating plain card. The CTA is an outlined/ghost button, never a
     filled gradient pill — understated by design. Every text/image field
     comes from $content, resolved+whitelisted by
     ThemePreset::landingContent() against LandingHeroLibrary's schema for
     'noir-reserve' — nothing here is hardcoded except the surrounding
     structure and the closing quiet feature list (not yet part of the
     editable schema, same discipline as every sibling theme). Fully
     responsive: the margin gutter collapses on mobile, text and photo
     stack in one quiet column in the order the admin picked. --}}
@php
    $radiusClass = match ($content['image_radius'] ?? 'xl') {
        'none' => 'rounded-none', 'md' => 'rounded-lg', 'full' => 'rounded-full', default => 'rounded-2xl',
    };
    $imageOnLeft = ($content['image_position'] ?? 'left') === 'left';
    $imageCentered = ($content['image_position'] ?? 'left') === 'center';
@endphp

<section class="relative overflow-hidden bg-[#F7F1EA] dark:bg-navy">
    <div class="relative mx-auto max-w-6xl px-6 pb-20 pt-16 sm:pb-24 sm:pt-20 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-6">
            {{-- Quiet decorative margin — desktop only, the "magazine margin" itself. --}}
            <div class="relative hidden lg:col-span-1 lg:block" aria-hidden="true">
                <div class="h-full border-r border-accent/20"></div>
            </div>

            <div class="{{ $imageCentered ? 'lg:order-1 lg:col-span-10 lg:col-start-2' : ($imageOnLeft ? 'lg:order-2 lg:col-span-5 lg:col-start-8' : 'lg:order-1 lg:col-span-5 lg:col-start-2') }}">
                <span class="inline-flex items-center gap-2 rounded-lg border border-accent/25 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-accent-dark dark:text-accent">
                    {{ $content['eyebrow'] }}
                </span>

                <h1 class="mt-6 max-w-lg font-display text-3xl font-semibold leading-[1.2] text-[#241D1A] sm:text-4xl lg:text-[2.6rem] dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-stone-600 dark:text-slate-300">
                    {{ $content['description'] }}
                </p>

                <div class="mt-8">
                    <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#241D1A] px-6 py-3 text-sm font-semibold text-[#241D1A] transition hover:bg-[#241D1A] hover:text-white dark:border-white/40 dark:text-white dark:hover:bg-white/10">
                        {{ $content['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div class="relative {{ $imageCentered ? 'lg:order-2 mt-12 lg:col-span-10 lg:col-start-2' : ($imageOnLeft ? 'lg:order-1 lg:col-span-5 lg:col-start-2' : 'lg:order-2 lg:col-span-5 lg:col-start-8') }}">
                <div class="relative {{ $imageCentered ? 'mx-auto max-w-lg' : '' }}">
                    @if ($content['image'])
                        <img src="{{ $content['image'] }}" alt="" class="w-full {{ $radiusClass }} object-cover shadow-xl">
                    @else
                        <div class="aspect-[4/5] w-full {{ $radiusClass }} bg-gradient-to-br from-primary via-primary-dark to-navy shadow-xl"></div>
                    @endif

                    {{-- Layered glass panel with a solid barrier layer — the stat
                         readout sits on a fully solid inner card, wrapped in a
                         translucent glass outer card, so the number stays
                         legible over any photo rather than relying on a thin
                         gradient wash. --}}
                    <div class="absolute -bottom-5 left-5 right-5 sm:left-6 sm:right-auto sm:w-56">
                        <div class="rounded-xl border border-white/15 bg-white/15 p-1.5 shadow-2xl backdrop-blur-md">
                            <div class="rounded-lg bg-[#241D1A]/95 px-4 py-3.5">
                                <p class="font-display text-xl font-bold leading-none text-white">{{ $content['stat_value'] }}</p>
                                <p class="mt-1 text-[11px] leading-snug text-white/70">{{ $content['stat_label'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quiet feature list — a hairline-topped row, never cards, echoing
             this persona's ledger-like restraint. --}}
        <div class="mt-24 border-t border-accent/15 pt-10 lg:pl-[calc(100%/12+1.5rem)]">
            <div class="grid gap-8 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'sim', 'title' => 'eSIM in seconds', 'body' => 'Scan a QR and you\'re connected — no store visit, nothing to explain.'],
                    ['icon' => 'phone', 'title' => 'A number that stays yours', 'body' => 'Voice and SMS that follow you, quietly, across every border.'],
                    ['icon' => 'wallet', 'title' => 'One wallet, considered pricing', 'body' => 'Data and numbers both draw from the same balance — nothing hidden.'],
                ] as $card)
                    <div class="flex gap-4">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-accent/25 text-accent-dark dark:text-accent">
                            <x-icon :name="$card['icon']" class="h-4 w-4" />
                        </span>
                        <div>
                            <h3 class="font-display text-base font-semibold text-[#241D1A] dark:text-white">{{ $card['title'] }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-stone-500 dark:text-slate-400">{{ $card['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
