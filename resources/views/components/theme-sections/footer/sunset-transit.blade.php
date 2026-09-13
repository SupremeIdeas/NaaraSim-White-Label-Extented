{{-- Swappable FOOTER — "sunset-transit" style family ("Boarding Pass",
     Theme Batch, 2026-09-07). Same functional content as
     components.site-footer (brand blurb, admin PRODUCT/COMPANY link
     columns, app-download slot, socials, copyright + legal row via
     SiteChrome) — reskinned in the airline-navy/warm-coral "boarding pass"
     persona already established on this theme's header/bottom-nav/login/
     landing/page surfaces.

     Section-boundary rule (owner request): a full-width row of PUNCHED-OUT
     CIRCULAR PERFORATIONS across the very top edge — the classic ticket-
     stub tear-line, cut with a CSS `mask-image` radial-gradient so the
     notches are true round die-cut holes, not polygon facets. Distinct
     from every sibling divider already used: solar-flare's diagonal slant,
     aries-contrast's thick gold rule, midnight-signal's single triangular
     notch, neon-vertex's rounded-top sheet, noir-reserve's single rounded
     top-left corner, origin-bold's thick colour-block border, and
     paperwhite's double hairline. A dashed coral "tear here" rule sits just
     under the perforation row, echoing the header/bottom-nav motif. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
    $maskCss = 'mask-image: radial-gradient(circle 7px at 16px 0, transparent 7px, black 7.5px);'
        .' mask-repeat: repeat-x; mask-size: 32px 100%; mask-position: top left;'
        .' -webkit-mask-image: radial-gradient(circle 7px at 16px 0, transparent 7px, black 7.5px);'
        .' -webkit-mask-repeat: repeat-x; -webkit-mask-size: 32px 100%; -webkit-mask-position: top left;';
@endphp

<footer data-footer-style="sunset-transit"
    @class([
        'relative isolate mt-8 overflow-hidden bg-navy text-slate-300',
        'pt-6' => $variant === 'full',
        'pt-5' => $variant !== 'full',
    ])
    style="{{ $maskCss }}">

    {{-- Dashed "tear here" rule, just under the punched perforation row. --}}
    <div class="pointer-events-none absolute inset-x-0 top-2.5 h-px" aria-hidden="true"
         style="background-image: repeating-linear-gradient(to right, rgb(var(--brand-accent)) 0 7px, transparent 7px 15px);"></div>

    <div class="pointer-events-none absolute -left-20 top-10 h-56 w-56 rounded-full bg-accent/10 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-16 bottom-0 h-48 w-48 rounded-full bg-primary/20 blur-3xl" aria-hidden="true"></div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-8 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300/80">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.2em] text-accent">
                        <x-icon name="plane" class="h-3 w-3 -rotate-45" /> {{ $col['heading'] }}
                    </p>
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
            <div class="relative z-10 border-t border-dashed border-white/15">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-accent/30 transition hover:bg-accent-dark">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-t border-dashed border-white/15">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full border border-dashed border-white/20 text-slate-300 transition hover:border-accent hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-dashed border-white/15">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-400 sm:flex-row">
            <x-footer-credit link-class="text-slate-200" />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
