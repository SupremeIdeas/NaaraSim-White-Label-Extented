{{-- Swappable BOTTOM NAV — "origin-bold" style family (Theme Batch 1
     follow-up, 2026-09-07). Persona: bold construction colour-block. A
     solid-fill docked bar with a thick top border, matching the header's
     colour-block treatment, and a square (not circular) centre button.
     Same inherited variables + Alpine contract as
     bottom-nav/default.blade.php. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t-4 border-navy bg-white transition-all duration-300 lg:hidden dark:border-white/20 dark:bg-navy"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-white shadow-lg shadow-primary/40 ring-4 ring-white transition active:scale-95 dark:ring-navy">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
