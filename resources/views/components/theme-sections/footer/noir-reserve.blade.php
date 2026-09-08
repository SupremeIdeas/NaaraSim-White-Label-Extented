{{-- Swappable FOOTER — "noir-reserve" style family (Theme visual rebuild,
     brand-new persona, 2026-09-07: "please all themes too should have
     unique footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned quiet/glass in the espresso-
     brown/burnt-sienna "old-money" persona already established on this
     theme's header/bottom-nav/login.

     Section-boundary rule (owner request): neither neon-vertex's smooth
     rounded-top corners on BOTH sides, nor midnight-signal's centred
     triangular notch, nor paperwhite's double hairline — an ASYMMETRIC
     single large rounded corner: only the TOP-LEFT corner is deeply
     rounded (`rounded-tl-[4rem]`), the top-right stays square. A
     deliberately imperfect, editorial-feeling asymmetry, matching this
     persona's "quiet luxury with a personality" rather than a
     mirror-symmetric curve. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="noir-reserve" class="relative isolate mt-6 overflow-hidden rounded-tl-[4rem] border-t border-accent/15 bg-[#241D1A] text-[#CBBAAF]">
    {{-- A single, quiet warm glow — never the multi-blob backdrops the
         louder personas use. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="absolute -bottom-24 left-12 h-64 w-64 rounded-full bg-accent/10 blur-3xl"></span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-6 pb-14 pt-16 sm:px-8 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-[#B7A399]">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-4 font-display text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $col['heading'] }}</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="text-[#CBBAAF] transition hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1) —
             a quiet outline pill, matching this persona's understated CTAs. --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative z-10 border-t border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-6 py-5 sm:px-8">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-accent/40 px-5 py-2.5 text-sm font-semibold text-accent transition hover:bg-accent/10">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-6 py-4 sm:px-8">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-lg border border-accent/20 text-[#CBBAAF] transition hover:border-accent/40 hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-5 text-xs text-[#9C8A80] sm:flex-row sm:px-8">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-[#CBBAAF]">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
