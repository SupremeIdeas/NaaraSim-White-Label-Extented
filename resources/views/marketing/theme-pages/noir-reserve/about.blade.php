{{-- Per-theme custom About page — "noir-reserve" (Theme visual rebuild,
     brand-new persona, 2026-09-07). Persona: "Espresso-brown with a
     burnt-sienna accent — quiet, tactile, old-money luxury." Every section
     is a genuinely different composition from every sibling theme's about
     page (neon-vertex's split-wordmark/card-fan/founder-split-panel,
     midnight-signal's annotated-diagram/gradient-band/pull-quote-band,
     paperwhite's single running editorial column, aries-contrast's
     hard-split ledger):
       - Hero: an offset headline in a narrow column next to a genuinely
         empty quiet margin gutter — the same "magazine margin" device as
         this theme's landing hero and login, carried through consistently
         across the whole persona rather than a one-off.
       - Mission/Vision: the margin gutter itself carries a WIDE PULL-QUOTE
         (the mission, set large in italic serif) sitting BESIDE the main
         narrative column (the vision + supporting copy) — not two side-
         by-side cards, not a gradient statement band, not a running-text
         interruption. The pull-quote genuinely occupies the margin zone
         other sections leave empty.
       - Founder: a quiet glass card using the LAYERED GLASS PANEL WITH A
         SOLID BARRIER LAYER trick (translucent outer card, solid inner
         card holding the actual bio text) instead of a colour-block split
         or a dark statement band. The founder avatar stays initials-only:
         no photo of Frank is on file, and a stock photo mislabelled with
         his name would misrepresent a real person.
       - A closing hairline-divided row (not cards, not a snap-scroll rail)
         states 3 quiet operating principles, matching the landing page's
         own feature-list treatment for persona consistency.
     Photo: team-coworking.webp (a quiet, warm-toned working room) — its
     literal content (people working calmly together, warm wood tones)
     matches this persona's "tactile, considered" copy better than a
     glossy device shot or a cold space photo, and its warm palette already
     echoes the espresso/burnt-sienna colour story. Downloaded once,
     committed as WebP under public/images/themes/shared/ already (owner
     rule: never a live hotlink). --}}
<section class="relative overflow-hidden bg-[#F7F1EA] dark:bg-navy">
    <div class="mx-auto max-w-6xl px-6 pb-14 pt-20 sm:pt-24 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="hidden lg:col-span-1 lg:block" aria-hidden="true">
                <div class="h-full border-r border-accent/20"></div>
            </div>
            <div class="lg:col-span-7 lg:col-start-2">
                <span class="inline-flex items-center gap-2 rounded-lg border border-accent/25 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-accent-dark dark:text-accent">
                    {{ $content['eyebrow'] }}
                </span>
                <h1 class="mt-6 max-w-xl font-display text-3xl font-semibold leading-[1.2] text-[#241D1A] sm:text-4xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-stone-600 dark:text-slate-300">
                    {{ $content['intro'] }}
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Mission (wide pull-quote, IN the margin) beside Vision (main narrative
     column) — the margin genuinely carries content here, unlike the hero
     above and the closing row below, where it stays empty. Pulled up over
     the hero's warm-ivory ground with a 30px rounded-top "sheet" edge
     (CLAUDE.md divider rule) rather than a flat seam, since the two
     sections sit on genuinely different backgrounds. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-white dark:bg-[#1C1512]">
    <div class="mx-auto max-w-6xl px-6 py-16 sm:py-20 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-8">
            <div class="lg:col-span-4">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-accent-dark dark:text-accent">Our mission</p>
                <p class="mt-4 font-display text-2xl italic leading-snug text-[#241D1A] sm:text-[1.75rem] dark:text-white">
                    &ldquo;{{ $content['mission'] }}&rdquo;
                </p>
            </div>
            <div class="border-t border-accent/15 pt-8 lg:col-span-7 lg:col-start-6 lg:border-l lg:border-t-0 lg:pl-10 lg:pt-0">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-stone-400 dark:text-slate-500">Our vision</p>
                <p class="mt-4 max-w-md text-lg leading-relaxed text-stone-700 dark:text-slate-200">
                    {{ $content['vision'] }}
                </p>
                <p class="mt-5 max-w-md text-sm leading-relaxed text-stone-500 dark:text-slate-400">
                    We measure that quiet in specifics: a rate locked before you pay, a QR that scans on the
                    first try, a number that survives a border crossing without a single dropped call.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Founder — layered glass panel with a solid barrier layer, over the
     team-coworking photo, rather than a colour-block split or dark band.
     Same 30px rounded-top overlap onto the mission/vision band above it. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-[#F7F1EA] py-20 dark:bg-navy">
    <div class="mx-auto max-w-4xl px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-2xl">
            <img src="{{ asset('images/themes/shared/team-coworking.webp') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-navy/80 via-navy/55 to-navy/85"></div>

            <div class="relative p-8 sm:p-12">
                {{-- Glass outer layer. --}}
                <div class="mx-auto max-w-lg rounded-xl border border-white/15 bg-white/10 p-1.5 shadow-2xl backdrop-blur-md">
                    {{-- Solid inner "barrier" layer — real contrast for the bio text. --}}
                    <div class="rounded-lg bg-[#241D1A]/95 p-6 sm:p-8">
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg border border-accent/40 font-display text-base text-accent">
                            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                        </span>
                        <p class="mt-5 text-sm leading-relaxed text-white/85">{{ $content['founder_bio'] }}</p>
                        <p class="mt-5 font-display text-sm font-semibold text-white">{{ $content['founder_name'] }}</p>
                        <p class="text-xs text-white/60">{{ $content['founder_title'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Closing operating principles — hairline-divided row, matching the
     landing page's own feature-list treatment for persona consistency. --}}
<section class="border-t border-accent/15 bg-[#F7F1EA] py-16 dark:bg-navy">
    <div class="mx-auto max-w-6xl px-6 lg:px-8">
        <div class="grid gap-8 sm:grid-cols-3 lg:pl-[calc(100%/12+1.5rem)]">
            @foreach ([
                ['icon' => 'shield-check', 'title' => 'Transparent, always', 'body' => 'The price you see is the price you pay — no hidden roaming fees, no fine print.'],
                ['icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended quietly to 190+ countries.'],
                ['icon' => 'sparkles', 'title' => 'Considered, not rushed', 'body' => 'Every rate and every route is checked before it ever reaches you.'],
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
</section>

@include('marketing._reused-sections')
