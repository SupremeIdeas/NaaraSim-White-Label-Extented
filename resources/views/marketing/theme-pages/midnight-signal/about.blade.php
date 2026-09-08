{{-- Per-theme custom About page — "midnight-signal" (Theme visual rebuild,
     owner request 2026-09-07). REWRITTEN (owner feedback: the first pass
     recoloured the same 2-card/3-card/founder-card skeleton every theme
     shared — "please stop giving me lazy man work"). Every section below
     is a different structural composition from neon-vertex's about page
     AND from a generic template, each traced to a specific reference:
       - Hero: an annotated device diagram — a duotone photo with 3
         labelled callout lines (AI Panym's annotated headset hero) on
         desktop; a plain icon list on mobile, where connector lines can't
         survive a narrow viewport.
       - Mission/Vision: one bold full-width statement band with a real
         Earth photo floated to one side (Payrot's parrot-plus-globe hero)
         instead of two plain side-by-side cards.
       - Values: a horizontal snap-scroll rail (ClassiAds), not a grid.
       - Founder: a dark statement band with a pull-quote over a duotone
         Earth backdrop (Payrot's "grow beyond borders" band) instead of a
         centred card.
     Every decorative photo slot ships with a real photo (owner rule: "no
     place will be empty... pick one from the Internet"), downloaded once
     and committed as WebP under public/images/themes/ rather than a live
     external hotlink (owner rule, follow-up: "lightweight, stored in our
     GitHub repo so we don't see a stale section... blank areas"). The
     founder avatar stays initials-only — no photo of him is on file, and
     a stock photo mislabelled with his name would misrepresent a real
     person. Section boundaries between different-coloured bands use a
     30px rounded-top "sheet" overlap (owner rule: never a flat straight
     divider between sections — "it will feel generic") instead of a flush
     line; sections that already share a colour (e.g. the hero and its own
     annotated-diagram row, both bg-navy) don't need one, since there's no
     seam to soften. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[26rem] w-[26rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/20 blur-3xl"></span>
    </div>

    <div class="relative mx-auto max-w-3xl px-4 pb-10 pt-16 text-center sm:pb-14 sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary">
            <x-icon name="signal" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['intro'] }}
        </p>
    </div>

    {{-- Annotated device diagram — desktop only; mobile gets a plain icon list below. --}}
    <div class="relative mx-auto hidden max-w-4xl items-center justify-center gap-6 px-4 pb-20 lg:flex">
        <div class="flex w-60 flex-col items-end gap-10 text-right">
            @foreach ([['icon' => 'signal', 'label' => 'Live carrier scan'], ['icon' => 'shield-check', 'label' => 'Predictable pricing']] as $point)
                <div class="flex items-center gap-3">
                    <p class="text-sm font-semibold text-white">{{ $point['label'] }}</p>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon :name="$point['icon']" class="h-4 w-4" /></span>
                    <span class="h-px w-10 bg-gradient-to-r from-primary/50 to-transparent"></span>
                </div>
            @endforeach
        </div>

        <div class="relative shrink-0 overflow-hidden rounded-[2.5rem] shadow-2xl">
            <img src="{{ asset('images/themes/shared/phone-screen.webp') }}" alt="" class="h-72 w-56 object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/40 to-primary/20 mix-blend-multiply"></div>
        </div>

        <div class="flex w-60 flex-col gap-10">
            <div class="flex items-center gap-3">
                <span class="h-px w-10 bg-gradient-to-l from-primary/50 to-transparent"></span>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon name="clock" class="h-4 w-4" /></span>
                <p class="text-sm font-semibold text-white">24/7 monitoring</p>
            </div>
        </div>
    </div>

    <div class="relative grid gap-3 px-4 pb-16 sm:grid-cols-3 lg:hidden">
        @foreach ([
            ['icon' => 'signal', 'label' => 'Live carrier scan'],
            ['icon' => 'shield-check', 'label' => 'Predictable pricing'],
            ['icon' => 'clock', 'label' => '24/7 monitoring'],
        ] as $point)
            <div class="mx-auto flex w-full max-w-xs items-center gap-3 rounded-2xl border border-primary/20 bg-navy/90 p-4 backdrop-blur">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary"><x-icon :name="$point['icon']" class="h-4 w-4" /></span>
                <p class="text-sm font-semibold text-white">{{ $point['label'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Mission / Vision — one bold statement band with a globe photo, not two
     side-by-side cards. Pulled up over the hero's navy with a 30px
     rounded-top "sheet" edge instead of a flat seam. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-gradient-to-br from-primary-dark to-primary px-4 py-16 sm:py-20">
    <div class="mx-auto grid max-w-5xl items-center gap-8 sm:grid-cols-[1fr_260px]">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-white/70">Mission</p>
            <p class="mt-2 text-xl font-bold leading-snug text-white sm:text-2xl">{{ $content['mission'] }}</p>
            <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-white/70">Vision</p>
            <p class="mt-2 text-base leading-relaxed text-white/90">{{ $content['vision'] }}</p>
        </div>
        <div class="mx-auto overflow-hidden rounded-[2.5rem] shadow-2xl sm:mx-0">
            <img src="{{ asset('images/themes/shared/earth-space.webp') }}" alt="" class="h-56 w-56 object-cover">
        </div>
    </div>
</section>

{{-- Values — horizontal snap-scroll rail. Same 30px rounded-top overlap
     onto the gradient band above it. --}}
<section class="relative -mt-8 rounded-t-[30px] bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <h2 class="px-4 text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">How we hold the signal</h2>
    <div class="mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:justify-center">
        @foreach ([
            ['icon' => 'signal', 'title' => 'Always watching', 'body' => 'Carrier quality and coverage are checked continuously, not once a quarter.'],
            ['icon' => 'shield-check', 'title' => 'No surprise bills', 'body' => 'What you see at checkout is the final price — cost is never marked up after the fact.'],
            ['icon' => 'clock', 'title' => 'Answers in minutes', 'body' => 'When a network hiccups, the signal room notices before you do.'],
        ] as $feature)
            <div class="w-64 shrink-0 snap-start rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon :name="$feature['icon']" class="h-4 w-4" />
                </span>
                <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $feature['title'] }}</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $feature['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Founder — dark pull-quote band over a duotone Earth backdrop. Same
     30px rounded-top overlap onto the light values band above it. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[30px] bg-navy px-4 py-20 text-center">
    <img src="{{ asset('images/themes/shared/earth-space.webp') }}" alt=""
         class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-15">
    <div class="relative mx-auto max-w-xl">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-lg font-bold text-primary">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <p class="mt-6 font-display text-xl font-bold leading-snug text-white sm:text-2xl">&ldquo;{{ $content['founder_bio'] }}&rdquo;</p>
        <p class="mt-5 text-sm font-semibold text-white">{{ $content['founder_name'] }}</p>
        <p class="text-xs text-primary">{{ $content['founder_title'] }}</p>
    </div>
</section>

@include('marketing._reused-sections')
