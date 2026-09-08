{{-- Swappable BOTTOM NAV — "sunset-transit" style family ("Boarding Pass",
     Theme Batch, 2026-09-07). Persona: airline-navy + warm coral. A docked
     ticket-strip bar on a paper-cream ground (dark mode: navy), divided
     into five ticket "segments" by DASHED VERTICAL PERFORATION rules
     (`divide-dashed`) instead of the default's plain gaps, origin-bold's
     solid border language, or midnight-signal's glass floating pill — and
     a coral ROUNDED-SQUARE "gate" button (dashed ring, echoing a die-cut
     ticket corner) standing in for every sibling's circular centre button.
     Same inherited variables + Alpine contract as bottom-nav/default.blade.php:
     $slots (4-item padded array), $isActive (closure). --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-dashed border-primary/25 bg-[#FBF7F0] transition-all duration-300 lg:hidden dark:border-white/15 dark:bg-navy"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center divide-x divide-dashed divide-primary/15 px-1 pt-1.5 dark:divide-white/10">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "gate" button — a rounded-square boarding marker, dashed
             ring, never a circle. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-2xl border-2 border-dashed border-white/60 bg-accent text-white shadow-lg shadow-accent/40 ring-4 ring-[#FBF7F0] transition active:scale-95 dark:ring-navy">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
