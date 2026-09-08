{{-- Per-theme custom About page — "solar-flare" (Theme Batch 2,
     2026-09-07). Structurally distinct from neon-vertex's split-wordmark +
     card-fan + snap-scroll composition and midnight-signal's console
     layout: this is a BENTO-GRID STAT WALL (sports-infographic reference —
     ESPN/League-standings-style dashboards mixing a hero statement tile
     with small hard-number tiles) — mission/vision/founder render as bold
     statement tiles of varying size alongside two numeric scoreboard tiles
     and one photo+number tile, rather than a run of identical plain cards.
     `$content`'s registered fields (eyebrow, headline, intro, mission,
     vision, founder_name/title/bio) are ALL rendered, verbatim, inside the
     grid — nothing invented, nothing dropped. The three small tiles are
     decorative flavour content (same precedent as neon-vertex's/midnight-
     signal's own extra hardcoded stat cards, not part of the editable
     schema); the photo tile uses a real committed WebP (owner rule: no
     empty placeholder where a photo fits), not reused by any other page in
     this suite. Founder avatar stays initials-only (no real photo on file
     — a stock photo of a stranger labelled with his name would
     misrepresent him).
     The grid areas are defined once in a small scoped `<style>` block (same
     technique as theme-sections/login-bg.blade.php's keyframes) and collapse
     to a single stacked column below `lg`, since named grid-template-areas
     read poorly through Tailwind's arbitrary-value syntax at that scale. --}}
@once
    <style>
        .nx-solar-bento {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 1024px) {
            .nx-solar-bento {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                grid-template-areas:
                    "mission mission stat-a stat-b"
                    "mission mission vision  vision"
                    "founder founder founder stat-c";
                grid-auto-rows: minmax(150px, auto);
            }
            .nx-solar-bento > [data-area="mission"] { grid-area: mission; }
            .nx-solar-bento > [data-area="stat-a"]  { grid-area: stat-a; }
            .nx-solar-bento > [data-area="stat-b"]  { grid-area: stat-b; }
            .nx-solar-bento > [data-area="vision"]  { grid-area: vision; }
            .nx-solar-bento > [data-area="founder"] { grid-area: founder; }
            .nx-solar-bento > [data-area="stat-c"]  { grid-area: stat-c; }
        }
    </style>
@endonce

{{-- 1. Hero — broadcast headline, no photo (the bento wall below carries the
     visual weight). --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0 opacity-[0.10]" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 34px);"></div>
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-20 text-center sm:pt-24">
        <span class="inline-flex items-center gap-1.5 border border-accent/60 bg-accent/15 px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-[0.15em] text-accent [clip-path:polygon(6%_0,100%_0,94%_100%,0_100%)]">
            <span class="relative flex h-1.5 w-1.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
            </span>
            {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-6 max-w-2xl font-display text-4xl font-black uppercase leading-[1.05] text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

{{-- 2. Bento-grid stat wall. --}}
<section class="mx-auto max-w-6xl px-4 pb-24 pt-14">
    <div class="nx-solar-bento">
        {{-- MISSION — the large statement tile. --}}
        <div data-area="mission" class="relative flex flex-col justify-between overflow-hidden border border-slate-200 bg-white p-7 shadow-sm dark:border-white/10 dark:bg-[#12172a]">
            <span class="absolute left-0 top-0 h-1.5 w-full bg-gradient-to-r from-primary to-accent" aria-hidden="true"></span>
            <div>
                <span class="flex h-10 w-10 items-center justify-center bg-gradient-to-br from-primary to-accent text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]">
                    <x-icon name="target" class="h-5 w-5" />
                </span>
                <p class="mt-4 text-xs font-extrabold uppercase tracking-[0.2em] text-primary-dark dark:text-primary">Mission</p>
                <p class="mt-3 font-display text-2xl font-bold leading-snug text-slate-900 dark:text-white sm:text-3xl">{{ $content['mission'] }}</p>
            </div>
        </div>

        {{-- STAT A --}}
        <div data-area="stat-a" class="flex flex-col justify-center border border-slate-200 bg-navy p-6 dark:border-white/10">
            <p class="font-display text-3xl font-black leading-none text-white sm:text-4xl">190+</p>
            <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Countries on the board</p>
        </div>

        {{-- STAT B --}}
        <div data-area="stat-b" class="flex flex-col justify-center border border-slate-200 bg-navy p-6 dark:border-white/10">
            <p class="font-display text-3xl font-black leading-none text-white sm:text-4xl">24/7</p>
            <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Live support coverage</p>
        </div>

        {{-- VISION --}}
        <div data-area="vision" class="relative overflow-hidden border border-slate-200 bg-gradient-to-br from-primary to-accent p-7 text-white shadow-sm">
            <span class="flex h-10 w-10 items-center justify-center bg-white/15 [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]">
                <x-icon name="eye" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-xs font-extrabold uppercase tracking-[0.2em] text-white/80">Vision</p>
            <p class="mt-3 font-display text-xl font-bold leading-snug sm:text-2xl">{{ $content['vision'] }}</p>
        </div>

        {{-- FOUNDER --}}
        <div data-area="founder" class="flex flex-col gap-5 border border-slate-200 bg-white p-7 dark:border-white/10 dark:bg-[#12172a] sm:flex-row sm:items-center">
            <div class="flex shrink-0 items-center gap-4">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center bg-gradient-to-br from-primary to-accent text-lg font-bold text-white [clip-path:polygon(15%_0,100%_0,85%_100%,0_100%)]">
                    {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
                <div class="sm:hidden">
                    <p class="font-display text-base font-bold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
                    <p class="text-xs font-medium text-primary-dark dark:text-primary">{{ $content['founder_title'] }}</p>
                </div>
            </div>
            <div class="min-w-0">
                <div class="hidden sm:block">
                    <p class="font-display text-base font-bold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
                    <p class="text-xs font-medium text-primary-dark dark:text-primary">{{ $content['founder_title'] }}</p>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
            </div>
        </div>

        {{-- PHOTO — a real committed photo behind the number, not an empty
             tile (owner rule); earth-space.webp fits "coverage" better than
             a desk/team shot here, and isn't reused by any other section of
             this suite. --}}
        <div data-area="stat-c" class="relative flex flex-col justify-end overflow-hidden border border-slate-200 p-6 dark:border-white/10">
            <img src="{{ asset('images/themes/shared/earth-space.webp') }}" alt=""
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy/90 via-navy/30 to-transparent"></div>
            <p class="relative font-display text-3xl font-black leading-none text-white sm:text-4xl">&lt;2min</p>
            <p class="relative mt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-200">Average activation</p>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
