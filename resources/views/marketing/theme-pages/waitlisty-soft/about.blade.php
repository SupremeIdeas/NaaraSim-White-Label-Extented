{{-- Per-theme custom About page — "waitlisty-soft" ("Horizon", Theme visual
     rebuild). Structurally distinct from midnight-signal's annotated-device-
     diagram/photo-band/snap-rail/dark-pull-quote sequence and from a generic
     template: this persona reads like a friendly founder-note page.
       - Hero: centred eyebrow + headline + intro, with a small circular
         team photo cluster underneath (not a diagram, not an icon list).
       - Mission/Vision: two TILTED "sticky note" cards side by side
         (slight opposite rotation, big oversized quote-mark glyphs) rather
         than one full-width statement band with a floated photo.
       - "Why people stick around": three soft pill cards in a row.
       - Founder: a two-up split — a big blob-shaped initials avatar on one
         side, the bio as a speech-bubble-style rounded card on the other —
         instead of a dark full-bleed pull-quote band.
     The photo (team-coworking.webp) is the same shared, locally-committed
     WebP already used on this theme's landing/login/contact pages — no
     live external hotlink, no empty placeholder. The founder avatar stays
     initials-only (no real photo of him on file). Section boundaries
     between different-background bands use the shared -mt-8 rounded-top
     "sheet" technique already established elsewhere in this codebase. --}}
<section class="relative overflow-hidden bg-[#FBF7FF] dark:bg-navy">
    <span class="pointer-events-none absolute left-1/2 top-0 h-80 w-80 -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/15 blur-3xl" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-2xl px-4 pb-10 pt-16 text-center sm:pb-14 sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-violet-200">
            <x-icon name="smile" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>

    <div class="relative mx-auto flex max-w-2xl justify-center gap-3 px-4 pb-16">
        <div class="h-20 w-20 overflow-hidden rounded-full ring-4 ring-white shadow-lg dark:ring-navy sm:h-24 sm:w-24">
            <img src="{{ asset('images/themes/shared/team-coworking.webp') }}" alt="" class="h-full w-full object-cover">
        </div>
        <div class="flex flex-col justify-center rounded-[1.5rem] bg-white px-4 py-2 shadow-md dark:bg-[#1c1329]">
            <p class="text-xs font-semibold text-slate-900 dark:text-white">A small team, a big soft spot for travellers</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Onitsha, Nigeria &middot; serving 190+ countries</p>
        </div>
    </div>
</section>

{{-- Mission / Vision — two tilted "sticky note" cards, not one photo band. --}}
<section class="relative -mt-8 rounded-t-[2.5rem] bg-white px-4 py-16 dark:bg-[#12081f] sm:py-20">
    <div class="mx-auto grid max-w-4xl gap-8 sm:grid-cols-2 sm:gap-10">
        <div class="relative -rotate-1 rounded-[2rem] bg-gradient-to-br from-primary/10 to-primary/5 p-7 shadow-[0_18px_40px_-26px_rgba(109,63,160,0.5)] dark:from-primary/15 dark:to-primary/5">
            <span class="font-display text-5xl leading-none text-primary/30">&ldquo;</span>
            <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-primary">Our mission</p>
            <p class="mt-2 text-lg font-semibold leading-snug text-slate-900 dark:text-white">{{ $content['mission'] }}</p>
        </div>
        <div class="relative rotate-1 rounded-[2rem] bg-gradient-to-br from-accent/15 to-accent/5 p-7 shadow-[0_18px_40px_-26px_rgba(232,121,249,0.45)] dark:from-accent/20 dark:to-accent/5">
            <span class="font-display text-5xl leading-none text-accent/40">&ldquo;</span>
            <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-accent-dark dark:text-accent">Our vision</p>
            <p class="mt-2 text-lg font-semibold leading-snug text-slate-900 dark:text-white">{{ $content['vision'] }}</p>
        </div>
    </div>
</section>

{{-- Why people stick around — three soft pill cards. --}}
<section class="relative -mt-8 rounded-t-[2.5rem] bg-[#FBF7FF] px-4 py-16 dark:bg-navy sm:py-20">
    <h2 class="text-center font-display text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">A few reasons people stick around</h2>
    <div class="mx-auto mt-10 grid max-w-4xl gap-4 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'heart', 'title' => 'Warm, not robotic', 'body' => 'Real people answer, and they actually like helping.'],
            ['icon' => 'shield-check', 'title' => 'No surprise charges', 'body' => 'The price you see is the price you pay — always.'],
            ['icon' => 'sparkles', 'title' => 'Made to feel easy', 'body' => 'If a step feels fiddly, we go back and simplify it.'],
        ] as $point)
            <div class="rounded-[1.75rem] bg-white p-5 text-center shadow-[0_14px_30px_-20px_rgba(109,63,160,0.4)] dark:bg-[#1c1329]">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                    <x-icon :name="$point['icon']" class="h-5 w-5" />
                </span>
                <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $point['title'] }}</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $point['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Founder — blob-shaped initials avatar + speech-bubble bio card. --}}
<section class="relative -mt-8 overflow-hidden rounded-t-[2.5rem] bg-white px-4 py-16 dark:bg-[#12081f] sm:py-20">
    <div class="mx-auto flex max-w-3xl flex-col items-center gap-8 sm:flex-row sm:items-start">
        <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-[42%_58%_65%_35%/48%_42%_58%_52%] bg-gradient-to-br from-primary to-accent text-2xl font-bold text-white shadow-xl">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <div class="relative rounded-[2rem] rounded-tl-none bg-[#FBF7FF] p-6 shadow-[0_14px_30px_-22px_rgba(109,63,160,0.4)] dark:bg-navy sm:rounded-tl-none">
            <p class="font-display text-lg font-semibold leading-snug text-slate-900 sm:text-xl dark:text-white">&ldquo;{{ $content['founder_bio'] }}&rdquo;</p>
            <p class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
            <p class="text-xs text-primary">{{ $content['founder_title'] }}</p>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
