{{-- Swappable BOTTOM NAV section — "default" style family (the original,
     unmodified global bottom nav). Resolved by
     ThemePreset::sectionStyle('bottom_nav') from components/app-shell.blade.php,
     wrapped there in the SAME @unless($inNumbers) gate as before — this
     partial only replaces the NAV MARKUP, never the Numbers-section
     mutual-exclusivity logic, so that conditional is unchanged. Depends on
     the parent view's Alpine `x-data` scope (moreOpen, navFloating) and the
     `$store.sectionNav` Alpine store — any other style family must keep
     using `moreOpen`/`navFloating`/`$store.sectionNav` exactly, since the
     "More" sheet and the near-footer reveal are shared across every style.
     Inherited variables: $slots (4-item padded array), $isActive (closure). --}}
<nav class="fixed z-40 border border-slate-200/70 bg-white transition-all duration-300 lg:hidden dark:border-white/10 dark:bg-navy"
     x-show="$store.sectionNav.globalVisible()" x-transition.opacity.duration.300ms
     :class="navFloating
        ? 'inset-x-3 bottom-3 rounded-[1.75rem] shadow-[0_10px_40px_rgba(13,27,42,0.16)] dark:shadow-[0_10px_40px_rgba(0,0,0,0.5)]'
        : 'inset-x-0 bottom-0 rounded-t-3xl border-b-0 shadow-[0_-10px_30px_rgba(13,27,42,0.10)] dark:shadow-[0_-10px_30px_rgba(0,0,0,0.4)]'"
     style="padding-bottom: env(safe-area-inset-bottom);">

    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
        @foreach ([$slots[0], $slots[1]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach

        {{-- Centre "More" button --}}
        <div class="flex justify-center">
            <button type="button" @click="moreOpen = true" aria-label="More"
                    class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dark text-white shadow-lg shadow-primary/30 ring-4 ring-[#F8F9FA] transition active:scale-95 dark:ring-navy">
                <x-icon name="grid" class="h-6 w-6" />
            </button>
        </div>

        @foreach ([$slots[2], $slots[3]] as $item)
            @include('partials.bottom-nav-item', ['item' => $item, 'isActive' => $isActive])
        @endforeach
    </div>
</nav>
