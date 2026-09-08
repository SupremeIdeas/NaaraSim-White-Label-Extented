@php
    $splash = \App\Support\SplashSettings::current();
@endphp
@if ($splash['enabled'])
    {{-- Opening splash (blueprint Section 24). The correct theme paints on the
         first frame via the pre-paint script in <head>, so the background never
         flashes the wrong colour.

         Light/dark logos are switched by CSS (Tailwind `dark:` classes), not
         JS: the light logo renders with `block dark:hidden`, the dark logo with
         `hidden dark:block`. So the logo built for a mode ONLY shows in that
         mode (a light-mode logo never bleeds into dark mode and vanish on the
         dark background), it's correct on the very first frame, and it flips
         instantly if the theme changes. A mode with no logo set simply shows
         the wordmark — never the wrong-contrast logo. --}}
    <div x-data="{
            visible: true,
            init() {
                if ({{ $splash['show_once_per_session'] ? 'true' : 'false' }} && sessionStorage.getItem('naara_splash_shown')) {
                    this.visible = false;
                    return;
                }
                sessionStorage.setItem('naara_splash_shown', '1');
                setTimeout(() => { this.visible = false }, {{ $splash['duration_ms'] }});
            }
         }"
         x-show="visible"
         x-transition:leave="transition-opacity ease-out duration-400"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#F8F9FA] dark:bg-navy"
         role="status" aria-label="{{ $splash['product_name'] }} loading">
        <div class="flex flex-col items-center">
            @if ($splash['product_logo_light'])
                <img src="{{ $splash['product_logo_light'] }}" alt="{{ $splash['product_name'] }}"
                     class="mb-3 block h-16 dark:hidden" fetchpriority="high">
            @endif
            @if ($splash['product_logo_dark'])
                <img src="{{ $splash['product_logo_dark'] }}" alt="{{ $splash['product_name'] }}"
                     class="mb-3 hidden h-16 dark:block" fetchpriority="high">
            @endif
            <span class="text-2xl font-bold text-primary-dark dark:text-primary">{{ $splash['product_name'] }}</span>
        </div>

        <div class="absolute bottom-10 flex items-center gap-2 opacity-70">
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $splash['brand_tagline'] }}</span>
            @if ($splash['brand_logo_light'])
                <img src="{{ $splash['brand_logo_light'] }}" alt="brand" class="block h-5 dark:hidden">
            @endif
            @if ($splash['brand_logo_dark'])
                <img src="{{ $splash['brand_logo_dark'] }}" alt="brand" class="hidden h-5 dark:block">
            @endif
        </div>
    </div>
@endif
