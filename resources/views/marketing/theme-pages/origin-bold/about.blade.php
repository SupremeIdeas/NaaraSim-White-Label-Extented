{{-- Per-theme custom About page — "origin-bold" (Theme visual rebuild,
     owner request 2026-09-07). Every section is its own colour-block
     composition, structurally distinct from neon-vertex's split-wordmark/
     card-fan/snap-rail pattern and midnight-signal's editorial layouts:
       - Hero: giant outlined-type watermark behind a bold uppercase
         headline (the login screen's "190+" motif language, reused here
         driven by the page's own eyebrow copy).
       - Photo band: a full-bleed solid-primary strip framing a real,
         hard-square-cornered photo (owner rule: never an empty
         placeholder) — team-coworking.webp, "built at scale."
       - Mission / Vision: colour-block HALVES, 50/50, each a full solid
         fill (primary / navy) carrying an oversized numeral (01/02) and a
         short bold statement instead of a paragraph card.
       - Founder: colour-block halves again, reversed ratio from the
         mission/vision split (a narrower fixed-width colour panel leading,
         a wider plain panel trailing) — the founder avatar stays
         initials-only (no photo on file for him yet; a stock photo
         labelled with his name would misrepresent a real person).
     Divider rule: every section boundary is a thick solid `border-t-8
     border-navy` colour-block edge — never a curve, never a flat
     undecorated line — matching this persona's established "no subtlety"
     language from the header/bottom-nav/login. --}}

{{-- 1. Hero — giant outlined-type watermark behind a bold headline. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden opacity-[0.05]" aria-hidden="true">
        <span class="select-none whitespace-nowrap font-display text-[5rem] font-black uppercase leading-none text-navy sm:text-[8rem] lg:text-[10rem] dark:text-white">{{ $content['eyebrow'] }}</span>
    </div>

    <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-20 text-center sm:pt-24">
        <span class="inline-flex items-center gap-2 border-2 border-navy bg-primary px-3 py-1 text-xs font-black uppercase tracking-widest text-white dark:border-white/20">
            {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-6 max-w-2xl font-display text-4xl font-black uppercase leading-[0.95] tracking-tight text-navy dark:text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

{{-- 2. Photo band — solid-primary strip framing a real, hard-cornered photo. --}}
<section class="border-t-8 border-navy bg-primary px-4 py-14 dark:border-white/20 sm:py-16">
    <div class="mx-auto max-w-4xl">
        <img src="{{ asset('images/themes/shared/team-coworking.webp') }}" alt="The NaaraSim team at work"
             class="mx-auto h-64 w-full max-w-3xl border-4 border-navy object-cover dark:border-white sm:h-80">
    </div>
</section>

{{-- 3. Mission / Vision — colour-block halves, 50/50, each with an
     oversized numeral instead of an icon. --}}
<section class="grid border-t-8 border-navy dark:border-white/20 lg:grid-cols-2">
    <div class="border-b-8 border-navy bg-navy p-10 text-white dark:border-white/20 lg:border-b-0 lg:border-r-8 sm:p-14">
        <span class="font-display text-6xl font-black leading-none text-white/25 sm:text-7xl">01</span>
        <h2 class="mt-4 text-xs font-black uppercase tracking-widest text-primary">Our mission</h2>
        <p class="mt-3 max-w-md font-display text-2xl font-bold leading-snug sm:text-3xl">{{ $content['mission'] }}</p>
    </div>
    <div class="bg-white p-10 text-navy dark:bg-white/5 dark:text-white sm:p-14">
        <span class="font-display text-6xl font-black leading-none text-navy/10 sm:text-7xl dark:text-white/10">02</span>
        <h2 class="mt-4 text-xs font-black uppercase tracking-widest text-primary">Our vision</h2>
        <p class="mt-3 max-w-md font-display text-2xl font-bold leading-snug sm:text-3xl">{{ $content['vision'] }}</p>
    </div>
</section>

{{-- 4. Founder — colour-block halves again, ratio reversed from the
     mission/vision split above (a narrow fixed colour panel leading, a
     wide plain panel trailing). Initials-only avatar: no real photo on
     file for the named founder. --}}
<section class="border-t-8 border-navy px-4 py-16 dark:border-white/20 sm:py-20">
    <div class="mx-auto grid max-w-3xl overflow-hidden border-4 border-navy dark:border-white/20 sm:grid-cols-[240px_1fr]">
        <div class="flex flex-col items-center justify-center gap-3 border-b-4 border-navy bg-primary p-8 text-center text-white dark:border-white/20 sm:border-b-0 sm:border-r-4">
            <span class="flex h-16 w-16 items-center justify-center border-2 border-white/60 text-xl font-black">
                {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
            </span>
            <p class="font-display text-base font-black leading-tight">{{ $content['founder_name'] }}</p>
            <p class="text-xs font-bold uppercase tracking-wide text-white/85">{{ $content['founder_title'] }}</p>
        </div>
        <div class="bg-white p-8 dark:bg-navy">
            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
