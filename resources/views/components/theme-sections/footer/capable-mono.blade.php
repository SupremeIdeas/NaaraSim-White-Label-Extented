{{-- Swappable FOOTER — "capable-mono" style family (Theme visual rebuild,
     owner request 2026-09-07: "please all themes too should have unique
     footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned in the capable-mono near-black
     monochrome persona: flat matte-black surface, hairline interior rules,
     monospace section labels, and lime reserved for hover states only (the
     footer's one AT-REST accent lives in the divider itself, never twice).

     Section-boundary rule (owner request): none of neon-vertex's rounded-top
     "sheet", solar-flare's diagonal clip-path, noir-reserve's asymmetric
     corner, origin-bold's thick colour-block border, or paperwhite's double
     hairline — this persona's own divider is a SINGLE THIN NEON-LIME
     HAIRLINE laid across an otherwise perfectly flush black-to-black seam:
     the quietest possible boundary treatment, wearing the persona's one
     permitted accent as the seam itself rather than as a shape. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="capable-mono" class="relative bg-primary text-white/50">
    {{-- The divider: a single 1px lime hairline, nothing else — the seam
         itself carries the accent instead of a shape. --}}
    <div class="h-px bg-accent" aria-hidden="true"></div>

    @if ($variant === 'full')
        <div class="relative mx-auto grid max-w-6xl gap-10 border-b border-white/10 px-4 pb-12 pt-14 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-white/40">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-white/30">// {{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="text-white/55 transition hover:text-accent">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative border-b border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-md border border-white/15 px-5 py-2.5 text-sm font-semibold text-white transition hover:border-accent hover:text-accent">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin
         has configured — quiet hairline squares, lime only on hover. --}}
    @if (! empty($socials))
        <div class="relative border-b border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-md border border-white/10 text-white/45 transition hover:border-accent hover:text-accent">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 font-mono text-[11px] text-white/30 sm:flex-row">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-white/50">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-accent">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
