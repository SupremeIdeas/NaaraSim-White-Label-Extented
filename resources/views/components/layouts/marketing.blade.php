{{-- Public marketing shell (Module 27): sticky glassy nav, CMS-driven pages,
     footer with Supreme Ideas Agency attribution + legal quick-links. Guests
     get Sign in / Get Started; signed-in visitors go straight to their
     dashboard. --}}
@props(['title' => null, 'description' => null, 'ogImage' => null, 'preloaderType' => 'marketing'])
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name().' — Stay Connected. No Borders. No Swaps.'" :page-type="$preloaderType" :description="$description" :og-image="$ogImage">
    <div class="mkt-bg min-h-screen">
        {{-- Floating, curved, glass header (Webflow-inspired): a contained pill
             that never touches the edges, with a few primary links inline and a
             Menu button that opens a full glass panel of everything. --}}
        @php
            // Mega-menu columns. Products = what Naara sells; Developers = the API;
            // Company = who we are. Naara Gift carries a "Soon" badge until its keys
            // are live (advertised now, links live automatically).
            $navProducts = [
                ['route' => 'pricing', 'label' => 'eSIM Data Plans', 'desc' => 'Local data, 190+ countries', 'icon' => 'globe'],
                ['route' => 'how-it-works', 'label' => 'How It Works', 'desc' => 'From purchase to connected', 'icon' => 'signal'],
            ];
            if (\App\Support\FeatureFlags::adminEnabled('naara_gift')) {
                $navProducts[] = ['route' => 'gift-cards', 'label' => 'Naara Gift', 'desc' => 'Gift cards for 1,000+ brands', 'icon' => 'gift',
                    'badge' => \App\Support\FeatureFlags::configured('naara_gift') ? null : 'Soon', 'authOnly' => true];
            }
            $navDevelopers = [
                ['route' => 'developers', 'label' => 'Developers', 'desc' => 'Resell via our API', 'icon' => 'key'],
                ['route' => 'pricing', 'label' => 'Pricing', 'desc' => 'Transparent, no surprises', 'icon' => 'credit-card'],
                ['route' => 'faq', 'label' => 'FAQ', 'desc' => 'Answers to common questions', 'icon' => 'help-circle'],
            ];
            $navCompany = [
                ['route' => 'about', 'label' => 'About', 'desc' => 'Our mission & story', 'icon' => 'info'],
                ['route' => 'blog', 'label' => 'Blog', 'desc' => 'News & guides', 'icon' => 'file-text'],
                ['route' => 'contact', 'label' => 'Contact', 'desc' => 'Talk to us', 'icon' => 'message-circle'],
            ];
        @endphp
        <header x-data="{ open: false }" class="pointer-events-none sticky top-0 z-50 px-3 pt-4 sm:px-4">
            <nav class="pointer-events-auto mx-auto flex max-w-5xl items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white px-2.5 py-2 shadow-xl shadow-slate-900/5 ring-1 ring-black/5 dark:border-white/10 dark:bg-[#0D1B2A] dark:ring-white/10">
                <a href="{{ route('home') }}" wire:navigate class="shrink-0 pl-1.5"><x-brand-logo variant="family" size="md" /></a>

                <div class="hidden items-center gap-7 text-sm font-medium text-slate-600 lg:flex dark:text-slate-300">
                    <a href="{{ route('how-it-works') }}" wire:navigate class="transition hover:text-primary">How It Works</a>
                    <a href="{{ route('pricing') }}" wire:navigate class="transition hover:text-primary">Pricing</a>
                    <a href="{{ route('developers') }}" wire:navigate class="transition hover:text-primary">Developers</a>
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="hidden nx-btn nx-btn--primary !py-2 !px-4 sm:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('register') }}" wire:navigate class="hidden nx-btn nx-btn--primary !py-2 !px-4 sm:inline-flex">Get Started</a>
                    @endauth

                    {{-- Menu button (animated bars → X) --}}
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Open menu"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white/60 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-primary hover:text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                        <span class="hidden sm:inline">Menu</span>
                        <span class="relative block h-3.5 w-5" aria-hidden="true">
                            <span class="absolute left-0 top-0 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'top-1.5 rotate-45'"></span>
                            <span class="absolute left-0 top-1.5 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'opacity-0'"></span>
                            <span class="absolute left-0 top-3 h-0.5 w-5 rounded bg-current transition-all duration-300" :class="open && 'top-1.5 -rotate-45'"></span>
                        </span>
                    </button>
                </div>
            </nav>

            {{-- Glass mega-menu panel --}}
            <div x-show="open" x-cloak x-transition.origin.top
                 @click.outside="open = false" @keydown.escape.window="open = false"
                 {{-- HOTFIX §7: real scroll container (was overflow-hidden with
                      no max-h → tall content clipped off-viewport on mobile).
                      §5: raised opacity so it's legible without backdrop-blur. --}}
                 class="pointer-events-auto mx-auto mt-2 max-h-[calc(100dvh-6rem)] max-w-5xl overflow-y-auto overscroll-contain rounded-2xl border border-slate-200/70 bg-white shadow-2xl shadow-slate-900/10 ring-1 ring-black/5 [-webkit-overflow-scrolling:touch] dark:border-white/10 dark:bg-[#0D1B2A] dark:ring-white/10">
                <div class="grid gap-6 p-5 sm:grid-cols-3 sm:p-6">
                    @foreach (['Products' => $navProducts, 'Developers' => $navDevelopers, 'Company' => $navCompany] as $heading => $items)
                        <div>
                            <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $heading }}</p>
                            @foreach ($items as $item)
                                @php $href = (($item['authOnly'] ?? false) && ! auth()->check()) ? route('register') : route($item['route']); @endphp
                                <a href="{{ $href }}" wire:navigate class="group flex items-start gap-3 rounded-xl px-2 py-2.5 transition hover:bg-primary/5 dark:hover:bg-white/5">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="{{ $item['icon'] ?? 'chevron-right' }}" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-900 group-hover:text-primary dark:text-slate-100">{{ $item['label'] }}
                                            @if ($item['badge'] ?? null)<span class="rounded-full bg-accent/15 px-1.5 py-px text-[9px] font-bold uppercase text-accent-dark dark:text-accent">{{ $item['badge'] }}</span>@endif
                                        </span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $item['desc'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                            {{-- Custom pages sit under Company. --}}
                            @if ($heading === 'Company')
                                @foreach (\App\Models\CustomPage::navLinks() as $navPage)
                                    <a href="{{ url('/p/'.$navPage['slug']) }}" class="group flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-primary/5 dark:hover:bg-white/5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-300"><x-icon name="file-text" class="h-4 w-4" /></span>
                                        <span class="text-sm font-semibold text-slate-900 group-hover:text-primary dark:text-slate-100">{{ $navPage['title'] }}</span>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 bg-white/40 px-5 py-3.5 dark:border-white/10 dark:bg-white/5">
                    <span class="text-xs font-medium uppercase tracking-widest text-slate-400">Stay Connected · No Borders</span>
                    <div class="flex items-center gap-2">
                        @auth
                            <a href="{{ route('dashboard') }}" wire:navigate class="nx-btn nx-btn--primary !py-2 !px-5">My Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" wire:navigate class="px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-primary dark:text-slate-300">Sign in</a>
                            <a href="{{ route('register') }}" wire:navigate class="nx-btn nx-btn--primary !py-2 !px-5">Get Started</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main>{{ $slot }}</main>

        {{-- Footer (Module 28: admin-assignable columns + legal via SiteChrome). --}}
        <x-site-footer variant="full" />
    </div>

    {{-- Floating navigation pill (Homepage floating-nav): admin-assignable slots +
         a glowing Wizard centrepiece, on desktop + mobile. On marketing there is
         no other bottom bar, so this is the sole floating element (no duplicate).
         The in-page Wizard is included only for signed-in visitors, so the
         centrepiece can open it; guests get a "Get started" CTA instead. --}}
    @auth
        @livewire('wizard')
    @endauth
    <x-floating-nav />

    {{-- ElevenLabs Convai voice assistant (task #17) — renders only when an admin
         enabled it for the public site. --}}
    <x-convai-widget context="marketing" />
</x-layouts.app>
