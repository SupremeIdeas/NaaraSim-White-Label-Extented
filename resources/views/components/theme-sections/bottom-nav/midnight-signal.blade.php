{{-- Swappable BOTTOM NAV — "midnight-signal" style family (Theme Batch 1
     follow-up, 2026-09-07). Persona: HUD/smart-home dark mode. A glass
     floating pill with a cyan glow ring around the centre button, matching
     the header's "console" treatment. Same inherited variables + Alpine
     contract as bottom-nav/default.blade.php. --}}
<nav class="fixed inset-x-3 bottom-3 z-40 rounded-[1.75rem] border border-primary/20 bg-white/90 backdrop-blur transition-all duration-300 lg:hidden dark:border-primary/25 dark:bg-navy/90"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-navy text-primary shadow-lg shadow-primary/40 ring-4 ring-primary/20 transition active:scale-95 dark:ring-primary/30">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
