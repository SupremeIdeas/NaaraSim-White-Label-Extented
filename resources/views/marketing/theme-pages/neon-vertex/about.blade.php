{{-- Per-theme custom About page — "neon-vertex" (Theme visual rebuild,
     owner request 2026-09-07). REWRITTEN (2026-09-07, owner feedback: the
     first pass recoloured the same generic 2-card/3-card/founder-card
     skeleton across every theme — "please stop giving me lazy man work").
     Every section below is a DIFFERENT structural composition, each traced
     to a specific researched reference rather than a template reuse:
       - Hero: giant split wordmark with a rotated photo breaking through
         the two lines (Forma Studio's "for | ma" chair-through-type hero).
       - Feature diagram: a centred photo with three labelled callout lines
         radiating out to floating tags (AI Panym's annotated headset hero)
         — a plain stacked list on mobile, since connector lines can't
         survive a narrow viewport.
       - Mission/Vision: a two-card FAN, each card rotated a few degrees
         and overlapping at the base (Nintendo eShop / eyewear-store
         card-fan heroes) — the rotation is dropped below `sm` so mobile
         gets two plain stacked cards instead of a fragile skew.
       - Values: a horizontal snap-scroll strip (ClassiAds' "Featured
         Listings" rail) instead of a static grid.
       - Founder: a colour-block split card (Payrot/Cmouse's panel+photo
         halves) instead of one centred stack.
     Every decorative photo slot ships with a real photo (owner rule,
     2026-09-07: "no place will be empty... pick one from the
     Internet... we will change the images later") — never an empty
     gradient box. Photos are downloaded once, converted to WebP, and
     committed under public/images/themes/ (owner rule, follow-up:
     "lightweight, stored in our GitHub repo so we don't see a stale
     section... blank areas") — never a live external hotlink, so the
     page never depends on a third-party host being reachable. The
     founder avatar stays initials-only: he has no photo on file yet, and
     a stock photo of a stranger labelled with his name would misrepresent
     a real person, which the "no empty" rule isn't asking for. --}}
@php
    $words = preg_split('/\s+/', trim($content['headline']));
    $mid = max(1, (int) ceil(count($words) / 2));
    $headlineLine1 = implode(' ', array_slice($words, 0, $mid));
    $headlineLine2 = implode(' ', array_slice($words, $mid)) ?: $headlineLine1;
@endphp

{{-- 1. Split-wordmark hero, photo breaking through the two lines. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-gradient-to-br from-primary/30 via-accent/25 to-transparent blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-20 text-center sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-300">
            <x-icon name="zap" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>

        <div class="relative mt-6">
            <h1 class="font-display font-black uppercase leading-[0.85] tracking-tight text-slate-900 dark:text-white" style="font-size: clamp(2.5rem, 10vw, 5.5rem);">
                {{ $headlineLine1 }}
            </h1>
            <div class="relative z-10 mx-auto my-3 w-32 rotate-[-5deg] sm:my-4 sm:w-44">
                <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}"
                     alt="" class="w-full rounded-[1.75rem] object-cover shadow-2xl ring-4 ring-white dark:ring-navy">
            </div>
            <h1 class="font-display font-black uppercase leading-[0.85] tracking-tight bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent" style="font-size: clamp(2.5rem, 10vw, 5.5rem);">
                {{ $headlineLine2 }}
            </h1>
        </div>

        <p class="mx-auto mt-10 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

{{-- 2. Annotated feature diagram — desktop gets connector-line callouts,
     mobile gets the same 3 points as a plain icon list. --}}
<section class="mx-auto max-w-5xl px-4 pb-20">
    <div class="hidden items-center justify-center gap-6 lg:flex">
        <div class="flex w-64 flex-col items-end gap-10 text-right">
            @foreach ([['icon' => 'zap', 'label' => 'Instant activation'], ['icon' => 'globe', 'label' => 'Coverage in 190+ countries']] as $point)
                <div class="flex items-center gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $point['label'] }}</p>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon :name="$point['icon']" class="h-4 w-4" /></span>
                    <span class="h-px w-10 bg-gradient-to-r from-primary/60 to-transparent"></span>
                </div>
            @endforeach
        </div>

        <div class="relative shrink-0">
            <img src="{{ asset('images/themes/shared/phone-screen.webp') }}"
                 alt="" class="h-72 w-56 rounded-[2.5rem] object-cover shadow-2xl">
        </div>

        <div class="flex w-64 flex-col gap-10">
            <div class="flex items-center gap-3">
                <span class="h-px w-10 bg-gradient-to-l from-accent/60 to-transparent"></span>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent-dark dark:bg-accent/20 dark:text-accent"><x-icon name="wallet" class="h-4 w-4" /></span>
                <p class="text-sm font-semibold text-slate-800 dark:text-white">One wallet, one price</p>
            </div>
        </div>
    </div>

    {{-- Mobile: same 3 points, plain stacked list. --}}
    <div class="grid gap-3 sm:grid-cols-3 lg:hidden">
        @foreach ([
            ['icon' => 'zap', 'label' => 'Instant activation'],
            ['icon' => 'globe', 'label' => 'Coverage in 190+ countries'],
            ['icon' => 'wallet', 'label' => 'One wallet, one price'],
        ] as $point)
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon :name="$point['icon']" class="h-4 w-4" /></span>
                <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $point['label'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- 3. Mission / vision — a two-card fan. Rotation is a transform (doesn't
     affect layout box size), and the stagger/overlap comes from a small
     margin, not absolute positioning — so the section's height always
     matches the cards' real content, however long an admin's copy runs. --}}
<section class="relative mx-auto max-w-2xl px-4 pb-24 pt-6">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-center sm:gap-6">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-7 shadow-lg dark:border-white/10 dark:bg-[#12172a] sm:w-72 sm:-rotate-6 sm:shadow-2xl">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                <x-icon name="target" class="h-5 w-5" />
            </span>
            <h2 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">Our mission</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['mission'] }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200 bg-white p-7 shadow-lg dark:border-white/10 dark:bg-[#12172a] sm:mt-10 sm:w-72 sm:rotate-6">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-accent to-primary text-white">
                <x-icon name="eye" class="h-5 w-5" />
            </span>
            <h2 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">Our vision</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['vision'] }}</p>
        </div>
    </div>
</section>

{{-- 4. Values — horizontal snap-scroll rail, not a static grid. --}}
<section class="pb-20">
    <h2 class="px-4 text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">What we build on</h2>
    <div class="mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:justify-center">
        @foreach ([
            ['icon' => 'shield-check', 'title' => 'Radical transparency', 'body' => 'The price you see is the price you pay — no hidden roaming fees, ever.'],
            ['icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended to 190+ countries.'],
            ['icon' => 'sparkles', 'title' => 'Obsessed with speed', 'body' => 'From download to a working eSIM in under two minutes, every time.'],
        ] as $card)
            <div class="w-72 shrink-0 snap-start rounded-3xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                    <x-icon :name="$card['icon']" class="h-5 w-5" />
                </span>
                <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- 5. Founder — colour-block split card, not a centred stack. --}}
<section class="mx-auto max-w-3xl px-4 pb-24">
    <div class="grid overflow-hidden rounded-[2.5rem] border border-slate-200 dark:border-white/10 sm:grid-cols-[220px_1fr]">
        <div class="flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-primary to-accent p-8 text-center text-white">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/15 text-xl font-bold">
                {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
            </span>
            <p class="font-display text-base font-bold leading-tight">{{ $content['founder_name'] }}</p>
            <p class="text-xs font-medium text-white/85">{{ $content['founder_title'] }}</p>
        </div>
        <div class="bg-white p-8 dark:bg-[#12172a]">
            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
