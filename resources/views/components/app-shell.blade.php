@props([
    'primary' => [],      // up to 4 items for the mobile bottom bar + top of sidebar
    'more' => [],         // secondary items: the mobile "More" sheet + lower sidebar
    'brandLabel' => 'NaaraSim',
    'brandRoute' => null,
    'brandIcon' => 'signal',
    'promo' => false,     // customer shell only: promo card / banner in the More sheet
])

@php
    $isActive = fn ($route) => request()->routeIs($route);
    $allItems = array_merge($primary, $more);
    // Pad primary to 4 so the bottom bar stays balanced around the centre button.
    $slots = array_pad(array_slice($primary, 0, 4), 4, null);
    // Header brand: the umbrella Naara family mark everywhere, EXCEPT a product's
    // own surface (eSIM/numbers → NaaraSim, gifts → Naara Gift), so each stands
    // out (owner request). Chrome, not content — see App\Support\BrandContext.
    $headerBrand = \App\Support\BrandContext::headerLogo();

    // Numbers section scoping (Numbers overhaul §2/§5). ONE bidirectional
    // condition, re-evaluated on every render (survives wire:navigate, since the
    // layout is part of each navigation response): on /numbers/* the global nav +
    // standard header are hidden and the Numbers nav + wallet bar take their place;
    // everywhere else it's the reverse. Never two flags that could drift.
    $inNumbers = request()->routeIs('numbers.*');
    $numbersUnread = ($inNumbers && auth()->check())
        ? \App\Models\MessageThread::totalUnread(auth()->id()) : 0;
    $numbersWalletUsd = ($inNumbers && auth()->check())
        ? (float) (auth()->user()->wallet->usd_balance ?? 0) : 0.0;
    // 2 left + 2 right around the untouched centre "More".
    $numbersNav = [
        ['route' => 'numbers.contacts', 'label' => 'Contacts', 'icon' => 'users'],
        ['route' => 'numbers.forwarding', 'label' => 'Forwarding', 'icon' => 'phone-forwarded'],
        ['route' => 'numbers.dialer', 'label' => 'Dialer', 'icon' => 'phone'],
        ['route' => 'numbers.messages', 'label' => 'Messages', 'icon' => 'message-circle', 'badge' => $numbersUnread],
    ];
@endphp

<div x-data="{
        moreOpen: false,
        navCollapsed: localStorage.getItem('nx_nav_collapsed') === '1',
        navFloating: localStorage.getItem('nx_nav_floating') !== '0',
        moreLayout: localStorage.getItem('nx_more_layout') === 'list' ? 'list' : 'grid',
     }"
     x-effect="localStorage.setItem('nx_nav_collapsed', navCollapsed ? '1' : '0'); localStorage.setItem('nx_nav_floating', navFloating ? '1' : '0'); localStorage.setItem('nx_more_layout', moreLayout)"
     @nx-nav-style.window="navFloating = $event.detail.floating"
     class="min-h-screen">
    {{-- ============ DESKTOP: Apple-inspired floating side menu ============
         Collapsible: the toggle shrinks it to an icon-only rail and back to
         icons + labels. The choice is remembered in localStorage. --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:flex lg:w-72 lg:flex-col lg:p-3 lg:transition-[width] lg:duration-300"
           :class="navCollapsed ? 'lg:!w-24' : ''">
        <div @class([
                'flex h-full flex-col rounded-3xl border border-slate-200/70 bg-gradient-to-b from-primary/5 via-slate-50 to-slate-100 shadow-sm dark:border-white/10',
                'dark:from-[#16233d] dark:via-[#141f36] dark:to-[#111a2e]' => \App\Support\ThemePreset::slug() === \App\Support\ThemePreset::DEFAULT_SLUG,
                'dark:bg-none dark:bg-navy' => \App\Support\ThemePreset::slug() !== \App\Support\ThemePreset::DEFAULT_SLUG,
             ])>
            <div class="flex items-center py-5" :class="navCollapsed ? 'justify-center px-3' : 'justify-between px-5'">
                <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center" x-show="!navCollapsed">
                    <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="lg" :fallback-icon="$brandIcon" />
                </a>
                <button type="button" @click="navCollapsed = !navCollapsed"
                        :aria-label="navCollapsed ? 'Expand menu' : 'Collapse menu'" :aria-expanded="(!navCollapsed).toString()"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100/80 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/5">
                    <span class="transition-transform duration-300" :class="navCollapsed ? '' : 'rotate-180'">
                        <x-icon name="chevron-right" class="h-5 w-5" />
                    </span>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-2">
                @foreach ($allItems as $item)
                    @if (! empty($item['heading']))
                        {{-- Section label — groups complementary items (collapsed
                             sidebar shows a divider instead). --}}
                        <p x-show="!navCollapsed" class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $item['heading'] }}</p>
                        <div x-show="navCollapsed" x-cloak class="mx-auto my-2 h-px w-6 bg-slate-200 dark:bg-white/10"></div>
                    @else
                        <a href="{{ route($item['route']) }}" wire:navigate
                           :class="navCollapsed && 'justify-center'"
                           :title="navCollapsed ? @js($item['label']) : null"
                           @class([
                               'nx-navlink group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium',
                               'is-active' => $isActive($item['route']),
                               'text-slate-600 hover:bg-slate-100/80 dark:text-slate-300 dark:hover:bg-white/5' => ! $isActive($item['route']),
                           ])>
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                            <span class="truncate" x-show="!navCollapsed">{{ $item['label'] }}</span>
                            @if ($item['badge'] ?? null)
                                <span x-show="!navCollapsed" class="ml-auto rounded-full bg-accent/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="flex gap-2 border-t border-slate-200/70 p-3 dark:border-white/10"
                 :class="navCollapsed ? 'flex-col items-center' : 'items-center justify-between'">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" :title="navCollapsed ? 'Sign out' : null"
                            class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100/80 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-200">
                        <x-icon name="log-out" class="h-5 w-5 shrink-0" /> <span x-show="!navCollapsed">Sign out</span>
                    </button>
                </form>
                <x-theme-toggle />
            </div>
        </div>
    </aside>

    {{-- ============ MOBILE: top brand bar ============
         "Invisible until you need it" (BLUEPRINT-batch1-sections §1): a soft
         gradient-fade glass header with no hard border, so content scrolls up
         under it without a visible edge. Logo + actions stay fully opaque and
         carry a subtle drop-shadow (in .nx-header-fade) so they never lose
         contrast over busy content underneath. --}}
    @if ($inNumbers)
        {{-- §5 Numbers header: the standard header (logo, bell, toggle, hamburger)
             is replaced by a wallet-balance bar + top-up shortcut across all
             /numbers/* routes — the freed space Frank asked for. --}}
        <header class="nx-header-fade sticky top-0 z-30 flex items-center justify-between px-4 py-3 lg:hidden">
            <a href="{{ route('numbers.lines') }}" wire:navigate class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <x-icon name="signal" class="h-5 w-5 text-primary" /> Numbers
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('wallet') }}" wire:navigate class="flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1.5 text-sm font-bold text-primary transition hover:bg-primary/15 dark:bg-primary/20">
                    <x-icon name="wallet" class="h-4 w-4" /> ${{ number_format($numbersWalletUsd, 2) }}
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white"><x-icon name="plus" class="h-3 w-3" /></span>
                </a>
                {{-- Global "More" sheet stays reachable from inside the Numbers
                     section (the section nav's centre is now My Lines). --}}
                <button type="button" @click="moreOpen = true" aria-label="More"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">
                    <x-icon name="grid" class="h-4 w-4" />
                </button>
            </div>
        </header>
    @else
    {{-- Swappable HEADER section (owner request, 2026-09-07): admin can point
         this at any built style family via ThemePreset::sectionStyle('header')
         — 'default' is the original, unmodified markup below, so every
         existing theme keeps rendering today's exact header. --}}
    @include('components.theme-sections.header.'.\App\Support\ThemePreset::sectionStyle('header'))
    @endif

    {{-- ============ Page content ============ --}}
    <div class="lg:pl-72 lg:transition-[padding] lg:duration-300" :class="navCollapsed ? 'lg:!pl-24' : ''">
        {{-- Desktop top strip: header actions (e.g. the notification bell) sit
             top-right of the content, mirroring the mobile header. A SEPARATE
             slot from the mobile one so each Livewire instance has its own id. --}}
        @isset($headerActionsDesktop)
            <div class="nx-header-fade sticky top-0 z-30 hidden items-center justify-end gap-1 px-8 py-3 lg:flex">
                {{ $headerActionsDesktop }}
            </div>
        @endisset
        <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:px-8 lg:py-10 lg:pb-10">
            {{ $slot }}
        </main>
        {{-- Near-footer sentinel (BLUEPRINT-batch1-sections §6): reveals the global
             bottom nav only when the page end is in view, on pages that contain a
             storytelling carousel. Pages without one are unaffected. --}}
        <div x-init="$store.sectionNav.observeSentinel($el)" aria-hidden="true" class="h-px w-full"></div>
    </div>

    {{-- ============ MOBILE: bottom navigation (owner request — premium) ============
         Two styles the user chooses between: FLOATING (default) — a rounded-3xl
         pill lifted off the bottom edge with a shadow on all sides; or DOCKED —
         flush to the bottom with only the top corners rounded. The little grab
         handle toggles between them (also settable from account settings). --}}
    {{-- Swappable BOTTOM NAV section (owner request, 2026-09-07): admin can
         point this at any built style family via
         ThemePreset::sectionStyle('bottom_nav') — 'default' is the
         original, unmodified markup below. The Numbers-section
         mutual-exclusivity gate stays right here, unchanged, so no style
         family can ever show alongside the Numbers nav below. --}}
    @unless ($inNumbers)
        @include('components.theme-sections.bottom-nav.'.\App\Support\ThemePreset::sectionStyle('bottom_nav'))
    @endunless

    {{-- Numbers section nav (Numbers overhaul §2) — shown ONLY on /numbers/*,
         mutually exclusive with the global nav above via the same $inNumbers. --}}
    @if ($inNumbers)
    <nav class="fixed inset-x-3 bottom-3 z-40 rounded-[1.75rem] border border-slate-200/70 bg-white shadow-[0_10px_40px_rgba(13,27,42,0.16)] lg:hidden dark:border-white/10 dark:bg-navy dark:shadow-[0_10px_40px_rgba(0,0,0,0.5)]"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1 pt-1.5">
            @foreach ([$numbersNav[0], $numbersNav[1]] as $item)
                @include('partials.numbers-nav-item', ['item' => $item, 'isActive' => $isActive])
            @endforeach
            {{-- Centre hub: My Lines — the heart of the Numbers section (manage
                 everything you own). Elevated like the global "More" button. --}}
            <div class="flex justify-center">
                <a href="{{ route('numbers.lines') }}" wire:navigate aria-label="My Lines"
                   class="-mt-6 flex h-14 w-14 flex-col items-center justify-center rounded-full text-white shadow-lg shadow-primary/30 ring-4 ring-[#F8F9FA] transition active:scale-95 dark:ring-navy {{ request()->routeIs('numbers.lines') ? 'bg-gradient-to-br from-accent to-primary' : 'bg-gradient-to-br from-primary to-primary-dark' }}">
                    <x-icon name="signal" class="h-6 w-6" />
                </a>
            </div>
            @foreach ([$numbersNav[2], $numbersNav[3]] as $item)
                @include('partials.numbers-nav-item', ['item' => $item, 'isActive' => $isActive])
            @endforeach
        </div>
    </nav>
    @endif

    {{-- ============ MOBILE: "More" sheet ============ --}}
    <div x-show="moreOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" style="display:none;">
        {{-- HOTFIX §6: no backdrop-blur here — animating blur alongside the
             sheet's slide-up transform causes GPU-compositing artifacts on many
             Android builds. The dim alone is enough; the sheet is already opaque. --}}
        <div x-show="moreOpen" x-transition.opacity @click="moreOpen = false" class="absolute inset-0 bg-black/40"></div>
        <div x-show="moreOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto overscroll-contain rounded-t-3xl border-t border-slate-200/70 bg-white p-5 pb-9 shadow-2xl dark:border-white/10 dark:bg-navy"
             style="padding-bottom: calc(env(safe-area-inset-bottom) + 1.5rem); -webkit-overflow-scrolling: touch;">
            <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20"></div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">More</h2>
                <div class="flex items-center gap-1">
                    {{-- Grid / list display toggle, persisted per-user (BUILD-3 §6.7). --}}
                    <div class="mr-1 flex items-center gap-0.5 rounded-lg bg-slate-100 p-0.5 dark:bg-white/5">
                        <button type="button" @click="moreLayout = 'grid'" aria-label="Grid view"
                                :class="moreLayout === 'grid' ? 'bg-white text-primary shadow-sm dark:bg-white/15' : 'text-slate-400'"
                                class="rounded-md p-1.5 transition"><x-icon name="grid" class="h-4 w-4" /></button>
                        <button type="button" @click="moreLayout = 'list'" aria-label="List view"
                                :class="moreLayout === 'list' ? 'bg-white text-primary shadow-sm dark:bg-white/15' : 'text-slate-400'"
                                class="rounded-md p-1.5 transition"><x-icon name="list" class="h-4 w-4" /></button>
                    </div>
                    <button type="button" @click="moreOpen = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
                </div>
            </div>

            @if ($promo)
                @php($menuBanners = \App\Support\Banners::for('menu_sheet'))
                @if ($menuBanners->isNotEmpty())
                    <x-banner-zone placement="menu_sheet" class="mb-4" />
                @else
                    {{-- Default promo (Module 32 pick — ayman-ashine floating-light
                         card, rebuilt on brand): shown until the admin publishes a
                         banner for this zone. --}}
                    <div class="nx-float-card mb-4" aria-hidden="true">
                        <span class="nx-float-card__light"></span>
                        <span class="nx-float-card__ring"></span>
                        <div class="relative">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">{{ \App\Support\BrandSettings::name() }}</p>
                            <p class="mt-1.5 font-display text-lg font-bold leading-snug text-white">Stay Connected. No&nbsp;Borders. No&nbsp;Swaps.</p>
                            <p class="mt-1 text-xs text-slate-300">eSIM data + numbers for 190+ countries, in one wallet.</p>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Grid (4-col cards) or list (stacked rows), per the §6.7 toggle.
                 SOLID cards in both themes (owner request: no glass/blur on the
                 More sheet). The active item keeps its brand-tinted highlight;
                 icons render white on dark for contrast against the solid card. --}}
            <div :class="moreLayout === 'list' ? 'flex flex-col gap-2' : 'grid grid-cols-4 gap-3'">
                @foreach ($more as $item)
                    @continue(! empty($item['heading'])) {{-- headings are desktop-sidebar only --}}
                    <a href="{{ route($item['route']) }}" wire:navigate @click="moreOpen = false"
                       :class="moreLayout === 'list' ? 'flex-row items-center gap-3 p-3 text-left' : 'flex-col items-center gap-1.5 p-3 text-center'"
                       @class([
                           'flex rounded-2xl border transition',
                           'border-primary/30 bg-primary/10 dark:border-primary/40 dark:bg-primary/15' => $isActive($item['route']),
                           'border-slate-200 bg-slate-50 hover:bg-slate-100 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-inner-dark)] dark:hover:bg-[var(--brand-card-hover-dark)]' => ! $isActive($item['route']),
                       ])>
                        {{-- Icon: brand teal in light mode; white on dark for contrast. --}}
                        <x-icon :name="$item['icon']" class="h-6 w-6 shrink-0 text-primary dark:text-white" />
                        <span class="font-medium leading-tight text-slate-600 dark:text-slate-300"
                              :class="moreLayout === 'list' ? 'text-sm' : 'text-[11px]'">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-5">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10">
                    <x-icon name="log-out" class="h-5 w-5" /> Sign out
                </button>
            </form>
        </div>
    </div>
</div>
