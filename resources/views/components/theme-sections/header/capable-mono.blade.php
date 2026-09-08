{{-- Swappable HEADER — "capable-mono" style family (Theme visual rebuild,
     brand-new persona, 2026-09-07). Persona: "Near-black monochrome with a
     single neon-lime accent — restrained by day, electric by night." A flat
     matte-black bar (no colour block like origin-bold, no gradient fade like
     noir-reserve, no glass blur like the default) carrying only a 1px
     hairline bottom border. The ONE deliberate accent on this header is a
     small monospace "system status" chip — a lime status dot + label — a
     utility-tool readout rather than any other theme's badge/pulse motif.
     Same inherited variables as header/default.blade.php: $brandRoute,
     $headerBrand, $brandIcon, $headerActions (optional slot). --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between border-b border-white/10 bg-primary px-4 py-3 lg:hidden">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" theme="dark" size="md" :fallback-icon="$brandIcon" />
    </a>

    <div class="flex items-center gap-2">
        {{-- The single lime element on this screen: a quiet status readout,
             not a decorative badge — restraint is the persona. --}}
        <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/[0.04] px-2 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-white/45">
            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent shadow-[0_0_5px_rgba(198,255,0,0.85)]" aria-hidden="true"></span>
            Live
        </span>
        <div class="flex items-center gap-1 rounded-md border border-white/10 bg-white/[0.04] px-1 py-1">
            {{ $headerActions ?? '' }}
            @unless (isset($headerActions))<x-theme-toggle />@endunless
        </div>
    </div>
</header>
