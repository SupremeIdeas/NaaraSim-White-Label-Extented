{{-- Swappable FOOTER — "midnight-signal" style family (Theme visual rebuild,
     owner request 2026-09-07: "please all themes too should have unique
     footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned in the midnight-signal near-black
     HUD/console persona already established on this theme's header/login/
     landing/about/how-it-works/contact surfaces: cyan-teal accents, radar/
     signal motifs, uppercase-tracked "console" labels.

     Section-boundary rule (owner request): a distinct STRUCTURAL divider —
     a small triangular "signal pulse" notch cut into the top edge (CSS
     clip-path), with a signal badge sitting right at its tip — rather than
     neon-vertex's smooth rounded-top-corner treatment, so no two themes
     share the same divider shape. Footer stays bg-navy in both light and
     dark mode (this persona is dark-first, same as the shared default). --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="midnight-signal" class="relative isolate mt-6 overflow-hidden bg-navy pt-7 text-slate-300 [clip-path:polygon(0_0,42%_0,48%_28px,52%_28px,58%_0,100%_0,100%_100%,0_100%)]">
    {{-- Signal badge sitting right at the tip of the notch, poking up into
         whatever section precedes the footer on any page. --}}
    <div class="pointer-events-none relative z-20 mx-auto flex w-full max-w-6xl justify-center" aria-hidden="true">
        <span class="absolute -top-11 flex h-9 w-9 items-center justify-center rounded-full border border-primary/30 bg-navy text-primary shadow-[0_0_0_6px_rgba(34,211,238,0.10)]">
            <x-icon name="signal" class="h-4 w-4" />
        </span>
    </div>

    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-56 w-[36rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-9 md:grid-cols-4">
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
                    <p class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-primary">
                        <x-icon name="wifi" class="h-3 w-3" /> {{ $col['heading'] }}
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
            <div class="relative z-10 border-t border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-5 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/20">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/15 bg-primary/5 text-slate-300 transition hover:bg-primary/15 hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-slate-300">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4 rounded-full border border-primary/15 bg-primary/5 px-4 py-1.5">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
