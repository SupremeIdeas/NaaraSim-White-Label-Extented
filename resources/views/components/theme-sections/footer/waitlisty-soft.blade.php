{{-- Swappable FOOTER — "waitlisty-soft" style family ("Horizon", Theme
     visual rebuild, owner request 2026-09-07: "please all themes too
     should have unique footer too... not always we get a straight line
     footer"). Same functional content as components.site-footer (brand
     blurb, admin PRODUCT/COMPANY link columns, app-download slot, socials,
     copyright + legal row via SiteChrome) — reskinned in the waitlisty-soft
     violet/magenta "rounded everything" persona already established on
     this theme's header/bottom-nav/login/landing/page surfaces.

     Section-boundary rule (owner request): NOT neon-vertex's rounded-top
     "sheet" corner, NOT midnight-signal's triangular notch, NOT
     aries-contrast/origin-bold's thick colour-block border, NOT
     noir-reserve's single asymmetric corner, NOT paperwhite's double
     hairline — a full-width SOFT WAVE drawn with an inline SVG path,
     poking up above the footer's own top edge like a gentle blob crest,
     matching this persona's organic-blob decoration used everywhere else
     (header scoop, bottom-nav centre blob, login badge). --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="waitlisty-soft" class="relative isolate mt-14 overflow-visible bg-[#F5EDFB] pt-10 text-slate-600 dark:bg-[#1c1329] dark:text-slate-300">
    {{-- The soft wave crest — pokes above the footer's own box, so the
         section above shows through the dips and this footer's own colour
         fills the peaks, reading as one continuous wavy seam. --}}
    <div class="pointer-events-none absolute inset-x-0 -top-[52px] h-[56px] overflow-hidden sm:-top-[62px] sm:h-[66px]" aria-hidden="true">
        <svg viewBox="0 0 1440 74" preserveAspectRatio="none" class="h-full w-full">
            <path d="M0,38 C180,74 340,0 520,16 C700,32 860,74 1040,52 C1220,30 1320,8 1440,22 L1440,74 L0,74 Z" class="fill-[#F5EDFB] dark:fill-[#1c1329]"></path>
        </svg>
    </div>

    {{-- Two quiet colour blooms, echoing the landing hero's blob backdrop. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="absolute -top-6 left-1/3 h-40 w-40 rounded-full bg-primary/10 blur-3xl"></span>
        <span class="absolute -bottom-10 right-10 h-48 w-48 rounded-full bg-accent/10 blur-3xl"></span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-6 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" size="lg" />
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 inline-flex items-center rounded-full bg-primary/10 px-3 py-1 font-display text-xs font-semibold uppercase tracking-wide text-primary dark:bg-primary/20 dark:text-violet-200">{{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="transition hover:text-primary dark:hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative z-10 border-t border-primary/10 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-primary to-accent px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:opacity-90">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-t border-primary/10 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/8 text-slate-500 transition hover:bg-primary/15 hover:text-primary dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-primary/10 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-400 dark:text-slate-500 sm:flex-row">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-slate-600 dark:text-slate-300">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-primary dark:hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
