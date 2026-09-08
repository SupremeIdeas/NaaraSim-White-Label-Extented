{{-- Swappable HEADER — "waitlisty-soft" style family ("Horizon", Theme
     visual rebuild). Persona: soft violet-purple with a magenta-pink pop,
     rounded-everything, warm consumer-app feel. Unlike neon-vertex's
     detached floating pill or midnight-signal's plain glass bar, this
     header stays DOCKED flush to the top edge but ends in a deep
     rounded-b-[2rem] "scoop" — like a soft tab torn off a rounded card —
     over a gentle pastel gradient wash, with an organic blob (not a
     circle) holding the brand mark. Same inherited variables as
     header/default.blade.php: $brandRoute, $headerBrand, $brandIcon,
     $headerActions (optional slot). --}}
<header data-header-root class="sticky top-0 z-30 flex items-center justify-between rounded-b-[2rem] bg-gradient-to-b from-primary/10 via-white to-white px-4 py-3.5 shadow-[0_14px_30px_-18px_rgba(109,63,160,0.55)] lg:hidden dark:from-primary/15 dark:via-navy dark:to-navy dark:shadow-[0_14px_30px_-18px_rgba(0,0,0,0.65)]">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center gap-2.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[45%_55%_60%_40%/50%_45%_55%_50%] bg-gradient-to-br from-primary to-accent shadow-md shadow-primary/30">
            <x-icon name="smile" class="h-4 w-4 text-white" />
        </span>
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1 rounded-full bg-primary/8 p-1 dark:bg-white/5">
        {{ $headerActions ?? '' }}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
