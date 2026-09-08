{{-- Swappable BOTTOM NAV — "noir-reserve" style family (Theme visual
     rebuild, brand-new persona, 2026-09-07). Persona: quiet, tactile,
     old-money luxury. A muted glass pill — same floating treatment as
     neon-vertex/midnight-signal but toned all the way down (a warm-tinted
     glass, a hairline instead of a bright border) — with a soft burnt-
     sienna glow behind the ACTIVE tab only, not a bright highlight. The
     centre "More" button is a soft rounded SQUARE (this persona's own
     0.75rem card radius, never a full circle like every other theme's
     centre button) with a thin gold-brown ring rather than a solid fill.
     Same inherited variables + Alpine contract as bottom-nav/default.blade.php:
     $slots (4-item padded array), $isActive (closure), $store.sectionNav,
     moreOpen/navFloating from the parent's x-data. The shared
     partials.bottom-nav-item markup itself is never touched (it's used by
     every theme) — the muted accent glow is added here, in the wrapper
     this file owns, around whichever slot is currently active. --}}
<nav class="fixed inset-x-4 bottom-3 z-40 rounded-xl border border-accent/15 bg-[#F7F1EA]/90 shadow-[0_10px_30px_-8px_rgba(46,30,22,0.25)] backdrop-blur-md transition-all duration-300 lg:hidden dark:border-accent/15 dark:bg-navy/90 dark:shadow-[0_10px_30px_-8px_rgba(0,0,0,0.5)]"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            <div @class(['rounded-lg transition', 'bg-accent/10 shadow-[0_0_14px_-4px_rgba(184,92,56,0.55)]' => $item && $isActive($item['route'])])>
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
            </div>
        @endforeach

        {{-- Centre "More" button — a quiet rounded square, ring only. --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-xl border border-accent/40 bg-[#F7F1EA] text-accent-dark shadow-lg shadow-primary/20 ring-4 ring-[#F7F1EA] transition active:scale-95 dark:border-accent/30 dark:bg-navy dark:text-accent dark:ring-navy">
                <x-icon name="grid" class="h-5 w-5" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            <div @class(['rounded-lg transition', 'bg-accent/10 shadow-[0_0_14px_-4px_rgba(184,92,56,0.55)]' => $item && $isActive($item['route'])])>
                @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
            </div>
        @endforeach
    </div>
</nav>
