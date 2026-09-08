{{-- Swappable HEADER — "neon-vertex" style family (Theme Batch 1, 2026-09-07).
     Persona: "Deep ultraviolet with a hot-pink flash on near-black —
     nightlife, electronic, after-hours energy." A floating glass PILL header
     (detached from the top edge, like the bottom nav's floating style) with a
     soft neon gradient glow ring, rather than the default's edge-to-edge bar.
     Same inherited variables as header/default.blade.php. --}}
{{-- Blur is admin-controllable (header editor, 2026-09-07) — see the note
     in header/midnight-signal.blade.php. Bottom-corner radius overrides
     the longhand border-bottom-*-radius only, so an admin-set curve blends
     into (rather than replaces) this pill's own rounded-full top. --}}
<header data-header-root class="sticky top-2 z-30 mx-3 flex items-center justify-between rounded-full border border-accent/25 bg-white/90 px-4 py-2.5 shadow-[0_8px_30px_rgba(0,0,0,0.08)] lg:hidden dark:border-accent/30 dark:bg-navy/90 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center gap-2">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent shadow-lg shadow-accent/30 dark:shadow-accent/50">
            <x-icon name="zap" class="h-3.5 w-3.5 text-white" />
        </span>
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
