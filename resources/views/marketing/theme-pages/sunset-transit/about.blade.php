{{-- Per-theme custom About page — "sunset-transit" ("Boarding Pass", Theme
     Batch, 2026-09-07). Structurally distinct from solar-flare's bento-grid
     stat wall and noir-reserve's asymmetric magazine layout: mission and
     vision render as a TWO-LEG ITINERARY — a pair of ticket-stub cards
     labelled "Leg 1" / "Leg 2", one cream, one navy, each with a die-cut
     notch — and the founder renders as a CREW ID CARD (dashed border,
     `id-card` glyph, initials badge) rather than a bento tile. $content's
     registered fields (eyebrow, headline, intro, mission, vision,
     founder_name/title/bio) are ALL rendered, verbatim. Founder avatar
     stays initials-only (no real photo on file — a stock photo of a
     stranger labelled with his name would misrepresent him). --}}
<section class="relative overflow-hidden bg-navy pb-16 pt-20 sm:pt-24">
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-primary/25 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-accent/15 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-dashed border-accent/50 bg-accent/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-accent">
            <x-icon name="plane" class="h-3 w-3 -rotate-45" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-6 max-w-2xl font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl lg:text-[2.65rem]">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-xl text-base leading-relaxed text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

{{-- Cream sheet pulled up over the hero (rule 5(a) treatment, matching the
     landing page's own boundary shape). --}}
<section class="relative -mt-6 overflow-hidden rounded-t-[1.75rem] bg-[#FBF7F0] pb-24 pt-14 dark:bg-[#0F172A]">
    <div class="mx-auto max-w-5xl px-4">
        {{-- Two-leg itinerary: mission (Leg 1, cream) + vision (Leg 2, navy). --}}
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-primary/25 bg-white p-7 shadow-sm dark:border-white/15 dark:bg-white/5">
                <span class="pointer-events-none absolute -left-2.5 top-8 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-primary dark:text-white">
                    <span class="rounded-full bg-primary px-2 py-0.5 text-white">Leg 1</span> Mission
                </div>
                <span class="mt-4 flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white">
                    <x-icon name="target" class="h-5 w-5" />
                </span>
                <p class="mt-4 font-display text-xl font-bold leading-snug text-[#1B2A47] dark:text-white sm:text-2xl">{{ $content['mission'] }}</p>
            </div>

            <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-accent/40 bg-navy p-7 text-white shadow-sm">
                <span class="pointer-events-none absolute -left-2.5 top-8 h-5 w-5 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-accent">
                    <span class="rounded-full bg-accent px-2 py-0.5 text-white">Leg 2</span> Vision
                </div>
                <span class="mt-4 flex h-10 w-10 items-center justify-center rounded-xl bg-accent text-white">
                    <x-icon name="eye" class="h-5 w-5" />
                </span>
                <p class="mt-4 font-display text-xl font-bold leading-snug sm:text-2xl">{{ $content['vision'] }}</p>
            </div>
        </div>

        {{-- Crew ID card — founder profile. --}}
        <div class="relative mt-6 overflow-hidden rounded-2xl border-2 border-dashed border-primary/20 bg-white p-7 dark:border-white/10 dark:bg-white/5 sm:flex sm:items-center sm:gap-6">
            <span class="pointer-events-none absolute -left-2.5 top-1/2 h-5 w-5 -translate-y-1/2 rounded-full bg-[#FBF7F0] dark:bg-[#0F172A]" aria-hidden="true"></span>

            <div class="flex shrink-0 items-center gap-4">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-primary/30 bg-primary/10 text-lg font-bold text-primary dark:border-white/20 dark:bg-white/10 dark:text-white">
                    {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
                <div class="sm:hidden">
                    <p class="font-display text-base font-bold text-[#1B2A47] dark:text-white">{{ $content['founder_name'] }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $content['founder_title'] }}</p>
                </div>
            </div>

            <div class="mt-4 min-w-0 sm:mt-0">
                <div class="hidden items-center gap-2 sm:flex">
                    <x-icon name="id-card" class="h-4 w-4 text-primary dark:text-white" />
                    <p class="font-display text-base font-bold text-[#1B2A47] dark:text-white">{{ $content['founder_name'] }}</p>
                    <span class="text-stone-400" aria-hidden="true">&middot;</span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-accent">{{ $content['founder_title'] }}</p>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
