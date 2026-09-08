{{-- Swappable BOTTOM NAV — "aurora-shift" style family (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" fintech-terminal energy.
     Structurally distinct from every sibling centre button (default/
     midnight-signal/neon-vertex's plain circle, noir-reserve's rounded
     square, solar-flare's skewed parallelogram, aries-contrast's own take):
     a HEXAGONAL "current node" — a circuit/network-node read, matching the
     "continuous data flow" persona — with the same flowing-gradient-line
     motif as the header running along the TOP edge of the docked bar in
     place of a static border. Active-tab glow is added via a scoped
     `:has()` rule (like solar-flare's) so the shared partials.bottom-nav-item
     markup stays untouched. Same inherited variables + Alpine contract as
     bottom-nav/default.blade.php: $slots (4-item padded array), $isActive
     (closure), $store.sectionNav, moreOpen/navFloating from the parent's
     x-data. --}}
@once
    <style>
        [data-bottom-nav="aurora-shift"] a:has(.text-primary) {
            filter: drop-shadow(0 0 7px rgb(var(--brand-accent) / 0.65));
        }
    </style>
@endonce
<nav data-bottom-nav="aurora-shift"
     class="fixed inset-x-3 bottom-3 z-40 overflow-hidden rounded-3xl border border-white/10 bg-navy/95 shadow-[0_14px_40px_-12px_rgba(0,0,0,0.65)] backdrop-blur-md transition-all duration-300 lg:hidden"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    {{-- The flowing current line, this persona's recurring motif, along the
         bar's top edge instead of a static border. --}}
    <div class="nx-aurora-current-line h-[2px] w-full" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pb-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "More" — a hexagonal current-node, not a circle/square/parallelogram. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center bg-gradient-to-br from-primary to-accent text-white shadow-lg shadow-primary/40 ring-4 ring-navy transition active:scale-95 [clip-path:polygon(50%_2%,95%_26%,95%_74%,50%_98%,5%_74%,5%_26%)]">
                <x-icon name="grid" class="h-5 w-5" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
