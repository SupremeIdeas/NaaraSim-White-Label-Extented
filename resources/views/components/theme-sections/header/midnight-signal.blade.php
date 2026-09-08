{{-- Swappable HEADER — "midnight-signal" style family (Theme Batch 1,
     2026-09-07). Persona: "Near-black smart-home dark mode, cyan-teal
     accents, dark-first design." A glass bar with a pulsing signal icon and
     header actions grouped inside a pill "console" — a HUD feel rather than
     the default's plain fade. Same inherited variables as header/default. --}}
{{-- Blur is admin-controllable (header editor, 2026-09-07): the built-in
     `backdrop-blur` (8px) is dropped from the class list here and instead
     supplied as this persona's own default via
     ThemePreset::HEADER_BLUR_DEFAULTS, so nothing visually changes until an
     admin actually moves the glass-depth slider. --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between border-b border-primary/20 bg-white/90 px-4 py-3 lg:hidden dark:border-primary/25 dark:bg-navy/90">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center gap-2">
        <span class="relative flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 dark:bg-primary/15">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary/30" aria-hidden="true"></span>
            <x-icon name="signal" class="relative h-3.5 w-3.5 text-primary" />
        </span>
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-1 py-1 dark:border-primary/25 dark:bg-primary/10">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
