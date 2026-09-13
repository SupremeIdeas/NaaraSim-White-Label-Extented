{{-- Swappable HEADER — "aries-contrast" style family (Theme Batch 1, 2026-09-07).
     Persona: "High-contrast black, white and gold, sharp corners — live-odds,
     luxury-travel energy." No glass/blur, no rounded corners, a thin gold
     rule instead of a soft fade. Same inherited variables as
     header/default.blade.php.
     2026-09-13: the decorative pulsing dot that used to sit before the
     wordmark was removed (owner correction) — it was purely decorative,
     redundant with the brand mark itself. --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between border-b-2 border-accent bg-white px-4 py-3 lg:hidden dark:bg-black">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center gap-2">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-2 border-l border-slate-200 pl-2 dark:border-white/15">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
