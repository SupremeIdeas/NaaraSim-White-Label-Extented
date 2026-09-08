{{-- Global glass sidebar (BUILD-3 §7). A slide-out, separate from the bottom
     "More" sheet, that always reaches the legal/compliance pages an app-store
     review needs — its contents are server-rendered here (and cached), so it
     works with no live network path through deep app state. Premium
     glassmorphism, reusing the app's existing translucent/blur treatment. --}}
@php
    $legal = \App\Support\SidebarMenu::legalLinks();
    $custom = \App\Support\SidebarMenu::customLinks();
    $reviews = \App\Support\SidebarMenu::reviewsUrl();
    $mode = \App\Support\SidebarMenu::displayMode();
    $social = \App\Support\SocialLinks::all();
    $posts = \App\Support\SidebarMenu::blogPosts();
    $linkGridClass = $mode === 'grid' ? 'grid grid-cols-2 gap-2' : 'flex flex-col gap-1.5';
    // Wear the same contextual brand mark as the top-bar/side-rail header (chrome
    // wears the product's mark on its own surface, the umbrella mark elsewhere —
    // App\Support\BrandContext) so the whole chrome stays consistent.
    $headerBrand = \App\Support\BrandContext::headerLogo();
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false" @open-global-sidebar.window="open = true">
    {{-- Trigger (sits beside the bell + theme toggle). --}}
    <button type="button" @click="open = true" aria-label="Open menu"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
        <x-icon name="menu" class="h-5 w-5" />
    </button>

    {{-- Teleport the overlay to <body> so `position: fixed` is viewport-relative.
         The trigger lives inside the header strip, which uses backdrop-blur — and
         an ancestor backdrop-filter traps `fixed` descendants inside its own box,
         which previously confined this panel to the header instead of overlaying
         the whole screen. x-teleport moves it out of that containing block. --}}
    <template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70]" style="display:none;">
        {{-- Backdrop — dims the whole screen, click to close. --}}
        <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-black/50"></div>

        {{-- Modal container: a bottom sheet on mobile, a centred modal on desktop
             (ChatGPT-style) — SOLID surface, smooth, easy (owner request). --}}
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                 @click.outside="open = false"
                 class="relative flex max-h-[88dvh] w-full flex-col overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl sm:max-h-[85dvh] sm:max-w-md sm:rounded-3xl dark:border-white/10 dark:bg-[#0D1B2A]"
                 style="padding-bottom: env(safe-area-inset-bottom);">

        {{-- Grab handle (mobile bottom-sheet affordance). --}}
        <div class="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-slate-300 sm:hidden dark:bg-white/20"></div>

        {{-- Header: brand mark + close. --}}
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10">
            <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" />
            <button type="button" @click="open = false" aria-label="Close menu"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-white/10 dark:hover:text-slate-200">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <div class="flex-1 space-y-7 overflow-y-auto overscroll-contain px-4 py-5">
            {{-- Legal & policies + any admin custom links — clean list rows. --}}
            <section>
                <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Legal &amp; policies</h3>
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
                    @foreach ($legal as $l)
                        <a href="{{ $l['url'] }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 border-b border-slate-100 bg-white px-3.5 py-3 text-sm font-medium text-slate-700 transition last:border-0 hover:bg-slate-50 dark:border-white/5 dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="shield" class="h-4 w-4" /></span>
                            <span class="flex-1 truncate">{{ $l['label'] }}</span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600" />
                        </a>
                    @endforeach
                    @foreach ($custom as $c)
                        <a href="{{ $c['url'] }}" @click="open = false"
                           class="flex items-center gap-3 border-b border-slate-100 bg-white px-3.5 py-3 text-sm font-medium text-slate-700 transition last:border-0 hover:bg-slate-50 dark:border-white/5 dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon :name="$c['icon'] ?: 'chevron-right'" class="h-4 w-4" /></span>
                            <span class="flex-1 truncate">{{ $c['label'] }}</span>
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600" />
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Ratings / reviews (app-store credibility). --}}
            @if ($reviews)
                <a href="{{ $reviews }}" target="_blank" rel="noopener" @click="open = false"
                   class="flex items-center justify-center gap-2 rounded-2xl bg-accent/15 px-3 py-3 text-sm font-semibold text-accent-dark transition hover:bg-accent/25 dark:bg-accent/15 dark:text-amber-300">
                    <x-icon name="star" class="h-4 w-4" /> Rate &amp; review us
                </a>
            @endif

            {{-- Blog widget (admin-toggled) — live from the Blog model. --}}
            @if ($posts->isNotEmpty())
                <section>
                    <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">From the blog</h3>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
                        @foreach ($posts as $post)
                            <a href="{{ route('blog.show', $post->slug) }}" wire:navigate @click="open = false"
                               class="block border-b border-slate-100 bg-white px-3.5 py-3 text-sm text-slate-700 transition last:border-0 hover:bg-slate-50 dark:border-white/5 dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/5">
                                <span class="line-clamp-2 font-medium">{{ $post->title }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Social handles. --}}
            @if (! empty($social))
                <section>
                    <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Follow us</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($social as $key => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($key) }}"
                               class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary/30 hover:bg-slate-50 hover:text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10">
                                <x-service-icon :slug="$key" class="h-5 w-5" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Account — deletion is fast + prominent (both app stores require it). --}}
        <div class="border-t border-slate-200 px-4 py-4 dark:border-white/10">
            <a href="{{ route('account') }}" wire:navigate @click="open = false"
               class="flex items-center justify-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-100 dark:border-red-500/30 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50">
                <x-icon name="trash" class="h-4 w-4" /> Delete my account
            </a>
        </div>
            </div>{{-- /modal card --}}
        </div>{{-- /modal container --}}
    </div>{{-- /fixed overlay --}}
    </template>
</div>
