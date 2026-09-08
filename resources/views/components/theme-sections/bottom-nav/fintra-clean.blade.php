{{-- Swappable BOTTOM NAV — "fintra-clean" style family ("Ledger" persona,
     Theme visual rebuild, 2026-09-07). Persona: dense tabular ledger, only
     lightly rounded. A flat DOCKED bar (never floating like neon-vertex/
     midnight-signal/noir-reserve) with thin vertical rules BETWEEN each of
     the 5 grid columns — the nav reads as one row of a spreadsheet rather
     than five loose icons — topped by a thin gold "total" rule instead of a
     thick border (origin-bold) or chamfered clip-path (solar-flare). The
     centre "More" button is a slate-navy, lightly-rounded SQUARE (this
     persona's own 0.375rem control radius, never a circle) so it reads as a
     ledger cell rather than an app-icon bubble. Same inherited variables +
     Alpine contract as bottom-nav/default.blade.php: $slots (4-item padded
     array), $isActive (closure), $store.sectionNav, moreOpen/navFloating
     from the parent's x-data. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white transition-all duration-300 lg:hidden dark:border-white/10 dark:bg-navy"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    {{-- Gold "total" rule — the ledger double-underline motif echoed from
         the header, at a smaller scale here since the bar itself already
         carries a hairline border above. --}}
    <div class="h-[2px] w-full bg-accent/80" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-md grid-cols-5 items-stretch divide-x divide-slate-200 px-1 dark:divide-white/10">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "More" — a lightly-rounded ledger-cell square, never a circle. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-5 flex h-11 w-11 items-center justify-center rounded-md border border-accent/60 bg-navy text-accent shadow-md transition active:scale-95">
                <x-icon name="grid" class="h-5 w-5" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
