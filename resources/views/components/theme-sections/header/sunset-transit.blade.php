{{-- Swappable HEADER — "sunset-transit" style family ("Boarding Pass",
     Theme Batch, 2026-09-07). Persona: "Airline-ticket navy with a warm
     coral pop — departures-board energy for a travel brand." A boarding-
     pass STUB bar: solid airline-navy ground holding the brand mark inside
     a dashed-border ticket chip and a coral "Boarding" status pill (a
     gate-readout, not a live-sports pulse), with a PERFORATED DASHED TEAR
     LINE running the full width beneath the bar — never solar-flare's
     angular clip-path cuts, aries-contrast's colour-block bar, or noir-
     reserve's soft fading hairline. The same tear-line motif recurs on the
     bottom nav and (as a punched-circle scallop) on the footer. Same
     inherited variables as header/default.blade.php: $brandRoute,
     $headerBrand, $brandIcon, $headerActions (optional slot). --}}
<header class="sticky top-0 z-30 lg:hidden">
    {{-- data-header-root sits on this inner div, not the outer <header> —
         see the note in header/default.blade.php for what this attribute
         is for (the header editor's colour/radius/glass overrides). --}}
    <div data-header-root class="flex items-center justify-between gap-2 bg-navy px-4 py-2.5">
        <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
            {{-- Ticket-stub brand chip: dashed border, die-cut corners. --}}
            <span class="flex h-9 shrink-0 items-center rounded-lg border border-dashed border-white/30 bg-white/10 px-2.5">
                <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" theme="dark" size="sm" :fallback-icon="$brandIcon" />
            </span>
            {{-- Coral "boarding" status pill — a gate-readout, not a pulse chip. --}}
            <span class="hidden shrink-0 items-center gap-1.5 rounded-full border border-dashed border-accent/50 bg-accent/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.15em] text-accent sm:inline-flex">
                <x-icon name="plane" class="h-3 w-3 -rotate-45" />
                Boarding
            </span>
        </a>
        <div class="flex shrink-0 items-center gap-1 rounded-full border border-white/15 bg-white/5 px-1 py-1">
            {{ $headerActions ?? '' }}
            @unless (isset($headerActions))<x-theme-toggle />@endunless
        </div>
    </div>
    {{-- Perforated tear-line — the recurring boarding-pass motif. --}}
    <div class="h-[3px] w-full bg-navy" aria-hidden="true"
         style="background-image: repeating-linear-gradient(to right, rgb(var(--brand-accent)) 0 7px, transparent 7px 15px);"></div>
</header>
