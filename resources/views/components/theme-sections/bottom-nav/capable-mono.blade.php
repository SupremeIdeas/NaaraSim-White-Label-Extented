{{-- Swappable BOTTOM NAV — "capable-mono" style family (Theme visual
     rebuild, brand-new persona, 2026-09-07). Persona: "Near-black monochrome
     with a single neon-lime accent — restrained by day, electric by night."
     A flat DOCKED bar (matching the header's flat matte-black language, not
     a floating pill like default/noir-reserve) whose active-tab indicator is
     a thin LIME SEGMENT LINE under the icon — the same "system readout"
     idiom as the header's status chip — rather than any colour fill,
     glow-behind-the-icon, or badge treatment used elsewhere. The centre
     "More" button stays circular (unlike noir-reserve's own deliberately
     square button) but flat black with a lime ring that only appears while
     the sheet is open, keeping the accent reserved for state, not
     decoration. Same inherited variables + Alpine contract as
     bottom-nav/default.blade.php: $slots (4-item padded array), $isActive
     (closure), $store.sectionNav, moreOpen/navFloating from the parent's
     x-data. The shared partials.bottom-nav-item markup is never touched —
     the indicator line is added here, in the wrapper this file owns. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-primary transition-all duration-300 lg:hidden"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-end px-1 pb-1.5 pt-2">
        @foreach ([$slots[0], $slots[1]] as $item)
            <div class="flex flex-col items-center">
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
                <span @class([
                    'mt-1 h-[3px] w-5 rounded-full transition',
                    'bg-accent shadow-[0_0_6px_rgba(198,255,0,0.7)]' => $item && $isActive($item['route']),
                    'bg-transparent' => ! $item || ! $isActive($item['route']),
                ]) aria-hidden="true"></span>
            </div>
        @endforeach

        {{-- Centre "More" button — flat black circle, lime ring only while
             the sheet is actually open (state, not decoration). --}}
        <div class="flex flex-col items-center justify-end pb-1.5">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full border border-white/15 bg-primary text-white shadow-[0_8px_24px_rgba(0,0,0,0.55)] ring-2 ring-transparent transition active:scale-95"
                    :class="moreOpen ? 'ring-accent' : 'ring-transparent'">
                <x-icon name="grid" class="h-5 w-5" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            <div class="flex flex-col items-center">
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
                <span @class([
                    'mt-1 h-[3px] w-5 rounded-full transition',
                    'bg-accent shadow-[0_0_6px_rgba(198,255,0,0.7)]' => $item && $isActive($item['route']),
                    'bg-transparent' => ! $item || ! $isActive($item['route']),
                ]) aria-hidden="true"></span>
            </div>
        @endforeach
    </div>
</nav>
