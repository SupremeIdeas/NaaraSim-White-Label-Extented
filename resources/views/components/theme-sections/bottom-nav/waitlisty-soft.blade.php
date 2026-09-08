{{-- Swappable BOTTOM NAV — "waitlisty-soft" style family ("Horizon", Theme
     visual rebuild). Persona: soft violet-purple/magenta-pink, rounded-
     everything, playful consumer feel. A floating pill like neon-vertex/
     midnight-signal/noir-reserve, but its own signature is the centre
     "More" button: an ORGANIC BLOB shape (asymmetric border-radius), never
     a perfect circle or square like every other theme's centre button —
     matching this persona's soft-blob decorative motif. Same inherited
     variables + Alpine contract as bottom-nav/default.blade.php:
     $slots (4-item padded array), $isActive (closure), $store.sectionNav,
     moreOpen from the parent's x-data. --}}
<nav class="fixed inset-x-3 bottom-3 z-40 rounded-[2rem] border border-primary/15 bg-white/95 shadow-[0_14px_36px_-16px_rgba(109,63,160,0.45)] backdrop-blur transition-all duration-300 lg:hidden dark:border-primary/20 dark:bg-navy/95 dark:shadow-[0_14px_36px_-16px_rgba(0,0,0,0.6)]"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "More" button — an organic blob, not a circle. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-7 flex h-14 w-14 items-center justify-center rounded-[42%_58%_62%_38%/48%_42%_58%_52%] bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-accent/40 ring-4 ring-white transition active:scale-95 dark:ring-navy">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
