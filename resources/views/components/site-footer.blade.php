@props(['variant' => 'full'])

{{-- Assignable footer (Module 28). Admin-managed link columns + legal row via
     SiteChrome; "Supreme Ideas Agency" attribution is a brand constant and is
     always shown. `variant="slim"` renders just the attribution + legal bar
     (used at the bottom of the auth pages); `full` adds the link columns.

     Swappable FOOTER section (owner request, 2026-09-07): admin can point
     this at any built style family via ThemePreset::sectionStyle('footer')
     — 'default' is the original, unmodified markup below (every existing
     theme, including naara-official, keeps today's exact footer), so this
     early-out is the ONLY change to this file. --}}
@if (\App\Support\ThemePreset::sectionStyle('footer') !== 'default')
    @include('components.theme-sections.footer.'.\App\Support\ThemePreset::sectionStyle('footer'), ['variant' => $variant])
@else
@php
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
@endphp

<footer {{ $attributes->merge(['class' => 'relative overflow-hidden border-t border-white/10 bg-navy text-slate-300']) }}>
    @if ($variant === 'full')
        {{-- Branded WebGL "water particles" backdrop (lazy, bundled, CSP-safe).
             Renders only while the footer is on-screen; the CSS glow is the
             reduced-motion / no-WebGL fallback. Footer is always navy, so no
             light-mode variant is needed. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span class="absolute bottom-0 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl"></span>
            <canvas data-webgl-hero="water"
                    class="absolute inset-0 h-full w-full opacity-0 transition-opacity duration-[1200ms] [&.is-live]:opacity-100"></canvas>
            <div class="absolute inset-0 bg-gradient-to-b from-navy via-navy/40 to-navy/80"></div>
        </div>
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-accent">{{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="transition hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative z-10 border-t border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has
         configured — hidden entirely until any account exists. --}}
    @php($socials = \App\Support\SocialLinks::forFooter())
    @if (! empty($socials))
        <div class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 text-slate-300 transition hover:bg-white/10 hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <x-footer-credit />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
@endif
