{{-- Swappable HEADER — "origin-bold" style family (Theme Batch 1,
     2026-09-07). Persona: "Vivid construction-orange with graphite accents,
     oversized type, confident colour blocks." A solid colour-block bar
     (never glass/fade) with a thick bottom border and an oversized wordmark
     treatment, in place of the default's soft translucent header. Same
     inherited variables as header/default.blade.php. --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between border-b-4 border-navy bg-primary px-4 py-3 lg:hidden dark:border-white/20">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" theme="dark" size="lg" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1 rounded-full bg-white/15 px-1 py-1">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
