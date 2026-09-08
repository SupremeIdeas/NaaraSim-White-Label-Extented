{{-- Swappable BOTTOM NAV — "solar-flare" style family (Theme Batch 2,
     2026-09-07). Persona: sports-broadcast scoreboard energy. A docked bar
     with chamfered top corners (a scoreboard-console cut, echoing the
     header's angular language) instead of the default's floating pill, a
     skewed parallelogram "More" button in place of the circular gradient
     one, and an amber glow that lights up whichever tab is active — added
     via a scoped `:has()` rule so the SHARED partials.bottom-nav-item stays
     completely untouched (it already marks the active link `text-primary`,
     which this rule keys off). Same inherited variables + Alpine contract
     as bottom-nav/default.blade.php: $slots (4-item padded array),
     $isActive (closure). --}}
@once
    <style>
        [data-bottom-nav="solar-flare"] a:has(.text-primary) {
            filter: drop-shadow(0 0 7px rgb(var(--brand-primary) / 0.65));
        }
    </style>
@endonce
<nav data-bottom-nav="solar-flare"
     class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-navy transition-all duration-300 lg:hidden [clip-path:polygon(14px_0,97%_0,100%_14px,100%_100%,0_100%,0_14px)]"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="h-[2px] w-full" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 8px, rgb(var(--brand-accent)) 8px 16px);"></div>

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "More" — a skewed parallelogram badge, not a circle. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-accent/40 ring-4 ring-navy transition active:scale-95 [clip-path:polygon(14%_0,100%_0,86%_100%,0_100%)]">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
