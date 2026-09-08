{{-- Swappable BOTTOM NAV — "neon-vertex" style family (Theme Batch 1
     follow-up, 2026-09-07). Persona: nightlife/electronic. A floating pill
     with a neon gradient glow centre button, matching the header's gradient
     badge. Same inherited variables + Alpine contract as
     bottom-nav/default.blade.php. --}}
<nav class="fixed inset-x-3 bottom-3 z-40 rounded-[1.75rem] border border-accent/25 bg-white/90 shadow-[0_8px_30px_rgba(0,0,0,0.08)] backdrop-blur transition-all duration-300 lg:hidden dark:border-accent/30 dark:bg-navy/90 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-accent/40 ring-4 ring-[#F8F9FA] transition active:scale-95 dark:ring-navy">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
