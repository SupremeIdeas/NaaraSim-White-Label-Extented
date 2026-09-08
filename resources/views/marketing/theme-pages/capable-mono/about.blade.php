{{-- Per-theme custom About page — "capable-mono" (Theme visual rebuild,
     owner request 2026-09-07). Structurally the opposite of paperwhite's
     single running editorial column and noir-reserve's asymmetric photo
     panel: a PRODUCT DOSSIER / spec-sheet composition — a monospace "README"
     card holding the intro, a two-cell MISSION/VISION spec table (bordered
     grid rows, not pull-quotes or boxed callouts), one real photo
     (phone-screen.webp) shown desaturated to grayscale so it reads as part
     of the monochrome system rather than a colour break, and a founder
     block styled as a bordered "profile" card with a quiet initials mark —
     no photo of Frank is on file, and per the owner's own placeholder rule
     an initials mark is the honest choice for a specific named real person.
     Lime is used exactly once on the page: a single status dot on the
     README card, echoing the header/landing "system status" idiom. --}}
<section class="bg-[#F8F9FA] dark:bg-navy">
    <div class="mx-auto max-w-3xl px-6 pb-4 pt-24 sm:pt-28">
        <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-2.5 py-1 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:border-white/10 dark:text-white/45">
            <span class="h-1.5 w-1.5 shrink-0 rounded-sm bg-slate-400 dark:bg-white/30" aria-hidden="true"></span>
            {{ $content['eyebrow'] }}
        </span>
        <h1 class="mt-6 max-w-xl font-display text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
    </div>
</section>

<article class="mx-auto max-w-3xl px-6 pb-24 pt-6">
    {{-- README card — a bordered console file, holding the intro paragraph. --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-primary">
        <div class="flex items-center justify-between border-b border-slate-200 bg-[#F1F2F3] px-5 py-2.5 dark:border-white/10 dark:bg-white/[0.03]">
            <span class="font-mono text-[11px] text-slate-400 dark:text-white/35">README.md</span>
            <span class="flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.18em] text-slate-400 dark:text-white/35">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                Status: live
            </span>
        </div>
        <p class="p-6 text-base leading-loose text-slate-600 dark:text-white/55 sm:p-8">
            {{ $content['intro'] }}
        </p>
    </div>

    {{-- Mission / Vision — a two-cell bordered spec table, not pull-quotes. --}}
    <div class="mt-6 grid overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10 sm:grid-cols-2">
        <div class="border-b border-slate-200 p-6 sm:border-b-0 sm:border-r sm:p-8 dark:border-white/10">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-white/30">// Mission</p>
            <p class="mt-3 text-base leading-relaxed text-slate-700 dark:text-white/70">{{ $content['mission'] }}</p>
        </div>
        <div class="p-6 sm:p-8">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-white/30">// Vision</p>
            <p class="mt-3 text-base leading-relaxed text-slate-700 dark:text-white/70">{{ $content['vision'] }}</p>
        </div>
    </div>

    {{-- One real photo, kept inside the monochrome system via a grayscale
         treatment rather than shown in full colour. --}}
    <figure class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
        <img src="{{ asset('images/themes/shared/phone-screen.webp') }}" alt=""
             class="h-64 w-full object-cover grayscale contrast-125 sm:h-80">
        <figcaption class="border-t border-slate-200 bg-white px-5 py-3 font-mono text-[11px] text-slate-400 dark:border-white/10 dark:bg-primary dark:text-white/35">
            The app, doing the one job it was built for.
        </figcaption>
    </figure>

    {{-- Founder — a bordered profile card, initials mark instead of a photo. --}}
    <div class="mt-6 flex items-start gap-4 rounded-2xl border border-slate-200 p-6 dark:border-white/10 sm:p-8">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-slate-300 font-display text-sm font-bold text-slate-700 dark:border-white/20 dark:text-white/70">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <div>
            <p class="font-display text-base font-semibold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
            <p class="font-mono text-xs text-slate-400 dark:text-white/35">{{ $content['founder_title'] }}</p>
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-white/55">{{ $content['founder_bio'] }}</p>
        </div>
    </div>
</article>

@include('marketing._reused-sections')
