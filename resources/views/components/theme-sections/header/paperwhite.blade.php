{{-- Swappable HEADER — "paperwhite" style family (Theme Batch 1, 2026-09-07).
     Persona: "Ultra-light, high-whitespace, ink-black type on paper —
     minimal, editorial, zero noise." No glass, no blur, no shadow — just a
     hairline rule and generous breathing room, the opposite treatment of
     every other header style. Same inherited variables as header/default. --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-[#F8F9FA] px-5 py-4 lg:hidden dark:border-white/10 dark:bg-navy">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
