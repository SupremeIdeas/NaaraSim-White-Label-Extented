{{-- Swappable BOTTOM NAV — "aries-contrast" style family (Theme Batch 1
     follow-up, 2026-09-07). Persona: sharp corners, high contrast. A
     square-cornered docked bar (never floating/rounded) with a sharp gold
     "More" button instead of the default's circular gradient one. Same
     inherited variables + Alpine contract as bottom-nav/default.blade.php. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-accent bg-white transition-all duration-300 lg:hidden dark:bg-black"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-12 w-12 items-center justify-center border-2 border-accent bg-black text-accent shadow-lg transition active:scale-95 dark:bg-white dark:text-black">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
