{{-- Per-theme custom About page — "paperwhite" (Theme visual rebuild,
     owner request 2026-09-07). Structurally the opposite of neon-vertex's
     split-wordmark/card-fan/founder-split-panel composition and
     midnight-signal's dark HUD equivalent: a SINGLE long-form editorial
     column, read top to bottom like a printed essay — no cards, no
     borders-as-boxes, no shadows anywhere on the page. Mission and vision
     interrupt the running prose as oversized italic pull-quote breaks
     (a real magazine-feature technique) rather than boxed callouts. The
     founder bio continues the same running text, introduced by a small
     quiet initials mark instead of a photo or a colour-block panel — no
     photo of Frank is on file, and per the owner's own placeholder rule an
     initials mark is the honest choice for a specific named real person.
     One real photo (team-coworking.webp) breaks the column once, inset and
     hairline-framed — used sparingly, in keeping with this theme's "zero
     noise" persona, not omitted entirely (owner rule: never fully empty of
     imagery). A first-letter drop cap opens the intro paragraph — a small,
     genuinely editorial typographic touch unique to this theme. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-2xl px-6 pb-6 pt-24 text-center sm:pt-28">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500">{{ $content['eyebrow'] }}</p>
        <h1 class="mx-auto mt-6 max-w-lg font-serif text-4xl italic leading-[1.15] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
    </div>
</section>

<article class="mx-auto max-w-xl px-6 pb-24 pt-6">
    <p class="text-lg leading-loose text-slate-600 dark:text-slate-300 first-letter:float-left first-letter:mr-2 first-letter:font-serif first-letter:text-6xl first-letter:font-medium first-letter:leading-[0.85] first-letter:text-slate-900 dark:first-letter:text-white">
        {{ $content['intro'] }}
    </p>

    {{-- One real photo, sparing and hairline-framed, breaking the column once. --}}
    <figure class="my-12">
        <img src="{{ asset('images/themes/shared/team-coworking.webp') }}" alt=""
             class="w-full border border-slate-200 object-cover dark:border-white/10" style="aspect-ratio: 16/9;">
        <figcaption class="mt-2.5 text-center font-serif text-xs italic text-slate-400 dark:text-slate-500">Where NaaraSim gets built — Onitsha, Nigeria.</figcaption>
    </figure>

    {{-- Mission — an oversized italic pull-quote break in the reading flow. --}}
    <blockquote class="my-14 border-y border-slate-200 py-8 text-center dark:border-white/10">
        <p class="mx-auto max-w-md font-serif text-2xl italic leading-snug text-slate-800 sm:text-3xl dark:text-slate-100">
            &ldquo;{{ $content['mission'] }}&rdquo;
        </p>
        <cite class="mt-4 block text-xs font-semibold not-italic uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500">Our mission</cite>
    </blockquote>

    <p class="text-base leading-loose text-slate-600 dark:text-slate-300">
        We measure that quietness in specifics: a rate locked before you pay, a QR that scans on the
        first try, a number that survives a border crossing without a single dropped text. None of it
        is flashy. All of it is deliberate.
    </p>

    {{-- Vision — a second pull-quote break, mirrored below the paragraph it answers. --}}
    <blockquote class="my-14 border-y border-slate-200 py-8 text-center dark:border-white/10">
        <p class="mx-auto max-w-md font-serif text-2xl italic leading-snug text-slate-800 sm:text-3xl dark:text-slate-100">
            &ldquo;{{ $content['vision'] }}&rdquo;
        </p>
        <cite class="mt-4 block text-xs font-semibold not-italic uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500">Our vision</cite>
    </blockquote>

    {{-- Founder — plain running text, introduced by a small quiet initials
         mark rather than a photo, card, or colour-block panel. --}}
    <div class="flex items-center gap-3 border-t border-slate-200 pt-10 dark:border-white/10">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-300 font-serif text-sm text-slate-700 dark:border-white/20 dark:text-slate-200">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <div>
            <p class="font-serif text-base italic text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $content['founder_title'] }}</p>
        </div>
    </div>
    <p class="mt-5 text-base leading-loose text-slate-600 dark:text-slate-300">
        {{ $content['founder_bio'] }}
    </p>
</article>

@include('marketing._reused-sections')
