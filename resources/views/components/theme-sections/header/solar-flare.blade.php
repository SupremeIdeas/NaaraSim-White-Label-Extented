{{-- Swappable HEADER — "solar-flare" style family (Theme Batch 2,
     2026-09-07). Persona: "Vivid amber-orange with a fierce crimson pop on
     deep navy — sports-broadcast energy and urgency." A live-broadcast
     scoreboard bar: solid deep-navy ground, the brand mark on a skewed
     parallelogram-cut badge, a pulsing "LIVE" chip next to it, and a thin
     amber/crimson diagonal-stripe accent rule under the whole bar in place
     of the default's soft glass fade. Same inherited variables as
     header/default.blade.php: $brandRoute, $headerBrand, $brandIcon,
     $headerActions (optional slot). --}}
<header class="sticky top-0 z-30 lg:hidden">
    {{-- data-header-root sits on this inner div, not the outer <header> —
         it's the element that actually carries the persona's background
         (see the note in header/default.blade.php for what this attribute
         is for). --}}
    <div data-header-root class="flex items-center justify-between gap-2 bg-navy px-4 py-2.5">
        <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
            {{-- Skewed parallelogram brand badge — the header's signature cut. --}}
            <span class="flex h-9 shrink-0 items-center bg-gradient-to-br from-primary to-primary-dark px-3 shadow-[0_4px_14px_-4px_rgb(var(--brand-primary)/0.7)] [clip-path:polygon(12%_0,100%_0,88%_100%,0_100%)]">
                <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" theme="dark" size="sm" :fallback-icon="$brandIcon" />
            </span>
            {{-- Pulsing "LIVE" chip — same angular cut, crimson accent. --}}
            <span class="hidden shrink-0 items-center gap-1.5 border border-accent/60 bg-accent/15 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.15em] text-accent [clip-path:polygon(10%_0,100%_0,90%_100%,0_100%)] sm:inline-flex">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-accent"></span>
                </span>
                Live
            </span>
        </a>
        <div class="flex shrink-0 items-center gap-1 border border-white/10 bg-white/5 px-1 py-1 [clip-path:polygon(8%_0,100%_0,92%_100%,0_100%)]">
            {{ $headerActions ?? '' }}
            @unless (isset($headerActions))<x-theme-toggle />@endunless
        </div>
    </div>
    {{-- Diagonal jersey-stripe accent rule — the persona's recurring motif,
         also echoed on the bottom nav and footer divider. --}}
    <div class="h-[3px] w-full" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 10px, rgb(var(--brand-accent)) 10px 20px); background-size: 28px 3px;"></div>
</header>
