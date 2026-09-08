{{-- Per-theme custom About page — "aries-contrast" (Theme visual rebuild,
     Batch 2, 2026-09-07). Persona established on this theme's header/
     bottom-nav/login: solid black/white, zero rounding, gold rule as the
     only decoration. Structurally different from neon-vertex's rotated-
     photo/card-fan/snap-rail composition and midnight-signal's annotated-
     diagram/gradient-statement-band composition:
       - Hero: a hard split — eyebrow badge, headline, intro on the left; a
         gold-framed, zero-radius photo on the right (never a photo
         breaking through rotated type or sitting inside a duotone band).
       - Mission/Vision: a two-row LEDGER — numbered rows separated by one
         thin gold horizontal rule, never cards or a coloured statement
         band.
       - Values: a 3-up hard-edge grid sharing gold rules (echoes the
         landing page's feature strip), on an inverted black/white band —
         not a snap-scroll rail.
       - Founder: a plain two-column bordered block, square initials
         avatar, zero rounding, no colour-block gradient panel.
     Photo: earth-space.webp (Earth at night, city-light networks) — its
     literal "a lit-up, verifiable global network" content matches this
     page's "built on hard numbers, not hype" copy better than a device or
     desk shot, and its warm city-light glow against black doubles as an
     on-brand gold-on-black moment. The founder avatar stays initials-only:
     no photo of him is on file, and a stock photo mislabelled with his
     name would misrepresent a real person. --}}
<section class="border-b-2 border-accent bg-white dark:bg-black">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:py-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <span class="inline-flex items-center gap-2 border-2 border-accent px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">
                    <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-accent"></span>
                    </span>
                    {{ $content['eyebrow'] }}
                </span>
                <h1 class="mt-6 font-display text-4xl font-black uppercase leading-[0.95] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
                    {{ $content['intro'] }}
                </p>
            </div>
            <div class="relative border-4 border-accent">
                <img src="{{ asset('images/themes/shared/earth-space.webp') }}" alt="" class="h-64 w-full object-cover sm:h-80">
                <span class="absolute bottom-0 left-0 border-r-2 border-t-2 border-accent bg-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-white">Verified network, live</span>
            </div>
        </div>
    </div>
</section>

{{-- Mission / Vision ledger — numbered rows, one gold rule between them. --}}
<section class="bg-white py-16 dark:bg-black sm:py-20">
    <div class="mx-auto max-w-4xl px-4">
        <div class="divide-y-2 divide-accent border-2 border-slate-900 dark:border-white">
            <div class="grid gap-3 p-6 sm:grid-cols-[80px_140px_1fr] sm:items-start sm:gap-4 sm:p-8">
                <span class="font-display text-3xl font-black text-accent">01</span>
                <div class="flex items-center gap-2">
                    <x-icon name="target" class="h-4 w-4 text-slate-900 dark:text-white" />
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">Mission</p>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400 sm:text-base">{{ $content['mission'] }}</p>
            </div>
            <div class="grid gap-3 p-6 sm:grid-cols-[80px_140px_1fr] sm:items-start sm:gap-4 sm:p-8">
                <span class="font-display text-3xl font-black text-accent">02</span>
                <div class="flex items-center gap-2">
                    <x-icon name="eye" class="h-4 w-4 text-slate-900 dark:text-white" />
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">Vision</p>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400 sm:text-base">{{ $content['vision'] }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Values — 3-up hard-edge grid, shared gold rules (echoes the landing
     page's feature strip), on an inverted black/white band. Not a
     snap-scroll rail. --}}
<section class="border-y-2 border-accent bg-black py-16 dark:bg-white sm:py-20">
    <div class="mx-auto max-w-6xl px-4">
        <h2 class="text-center font-display text-2xl font-black uppercase tracking-tight text-white dark:text-black sm:text-3xl">On the record</h2>
        <div class="mt-10 grid divide-y-2 divide-accent/50 sm:grid-cols-3 sm:divide-x-2 sm:divide-y-0">
            @foreach ([
                ['icon' => 'shield-check', 'title' => 'Radical transparency', 'body' => 'The price you see is the price you pay — no hidden roaming fees, ever.'],
                ['icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended to 190+ countries.'],
                ['icon' => 'badge-check', 'title' => 'Every stat sourced', 'body' => 'Coverage, pricing and uptime numbers are pulled live, never estimated.'],
            ] as $card)
                <div class="px-6 py-8 text-center sm:text-left">
                    <span class="mx-auto flex h-10 w-10 items-center justify-center border-2 border-accent text-accent sm:mx-0">
                        <x-icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                    <h3 class="mt-4 font-display text-lg font-bold uppercase tracking-tight text-white dark:text-black">{{ $card['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-white/70 dark:text-black/60">{{ $card['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Founder — plain bordered two-column block, square initials avatar, no
     colour-block gradient panel, zero rounding. --}}
<section class="bg-white py-16 dark:bg-black sm:py-20">
    <div class="mx-auto max-w-3xl px-4">
        <div class="grid overflow-hidden border-2 border-slate-900 dark:border-white sm:grid-cols-[180px_1fr]">
            <div class="flex flex-col items-center justify-center gap-3 border-b-2 border-accent bg-black p-8 text-center dark:bg-white sm:border-b-0 sm:border-r-2">
                <span class="flex h-16 w-16 items-center justify-center border-2 border-accent text-lg font-bold text-accent">
                    {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
                <p class="font-display text-sm font-bold leading-tight text-white dark:text-black">{{ $content['founder_name'] }}</p>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70 dark:text-black/60">{{ $content['founder_title'] }}</p>
            </div>
            <div class="p-8">
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $content['founder_bio'] }}</p>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
