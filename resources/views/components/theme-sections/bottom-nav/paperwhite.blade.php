{{-- Swappable BOTTOM NAV — "paperwhite" style family (Theme Batch 1
     follow-up, 2026-09-07). Persona: ultra-light editorial minimalism. No
     floating pill, no shadow, no blur — a flat docked bar with a single
     hairline top border, matching the header's restraint. Same inherited
     variables + Alpine contract as bottom-nav/default.blade.php. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-[#F8F9FA] transition-all duration-300 lg:hidden dark:border-white/10 dark:bg-navy"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-300 bg-[#F8F9FA] text-slate-700 transition active:scale-95 dark:border-white/20 dark:bg-navy dark:text-slate-200">
                <x-icon name="grid" class="h-5 w-5" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
