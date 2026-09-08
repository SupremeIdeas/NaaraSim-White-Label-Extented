{{-- Swappable HEADER — "noir-reserve" style family (Theme visual rebuild,
     brand-new persona, 2026-09-07). Persona: "Espresso-brown with a
     burnt-sienna accent — quiet, tactile, old-money luxury." A refined
     glass bar with a warm-dark tint (never stark navy, never a hard
     border) — the boundary is a single hairline that FADES at both ends
     rather than a solid rule, and the wordmark sits with extra breathing
     room either side instead of being crowded to the edges. No colour
     blocks, no sharp angles, no pulsing badges — the quietest header in
     the batch. Same inherited variables as header/default.blade.php:
     $brandRoute, $headerBrand, $brandIcon, $headerActions (optional slot). --}}
{{-- Blur is admin-controllable (header editor, 2026-09-07) — see the note
     in header/midnight-signal.blade.php. --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between bg-[#F7F1EA]/85 px-5 py-3.5 lg:hidden dark:bg-navy/80">
    {{-- Fading hairline instead of a hard border-bottom. --}}
    <span class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-accent/35 to-transparent" aria-hidden="true"></span>

    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center gap-2.5">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-accent/30 bg-accent/10 text-accent-dark dark:text-accent" aria-hidden="true">
            <span class="font-display text-xs italic">N</span>
        </span>
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
