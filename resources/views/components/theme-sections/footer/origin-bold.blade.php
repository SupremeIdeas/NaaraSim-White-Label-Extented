{{-- Swappable FOOTER — "origin-bold" style family (Theme visual rebuild,
     owner request 2026-09-07: "please all themes too should have unique
     footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned in the origin-bold construction-
     orange/graphite persona already established on this theme's header/
     bottom-nav/login: solid colour-block fills (never glass/fade), thick
     square-edged borders, uppercase black type, square (not circular)
     link/social buttons.

     Section-boundary rule (owner request): neither neon-vertex's rounded-
     top "sheet" nor midnight-signal's clip-path notch — this persona's own
     divider is a THICK solid colour-block border (`border-t-8 border-navy`,
     the same hard edge language as the header's `border-b-4 border-navy`
     and the bottom-nav's `border-t-4 border-navy`, just heavier here since
     the footer is the largest colour block on the page). --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="origin-bold" class="relative overflow-hidden border-t-8 border-navy bg-navy text-slate-300 dark:border-white/20">
    {{-- Oversized outlined-type watermark — the same giant-numeral/type
         language as the login screen's "190+" motif, reused here as a huge
         faint wordmark rather than a decorative gradient blob. --}}
    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center overflow-hidden opacity-[0.05]" aria-hidden="true">
        <span class="select-none whitespace-nowrap font-display text-[13rem] font-black uppercase leading-none text-white">190+</span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 border-b-4 border-white/10 px-4 pb-12 pt-14 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-3 inline-block border-b-2 border-primary pb-1 text-xs font-black uppercase tracking-widest text-white">{{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="transition hover:text-primary">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative z-10 border-b-4 border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 border-4 border-primary bg-primary px-5 py-2.5 text-sm font-black uppercase tracking-wide text-white transition hover:bg-primary-dark">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has
         configured — square blocks, not circles, matching this persona. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-b-4 border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center border-2 border-white/15 bg-white/5 text-slate-300 transition hover:border-primary hover:bg-primary hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <x-footer-credit link-class="font-semibold text-slate-300" />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
