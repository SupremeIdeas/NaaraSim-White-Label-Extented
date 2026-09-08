{{-- Swappable FOOTER — "solar-flare" style family (Theme Batch 2,
     2026-09-07). Same functional content as components.site-footer (brand
     blurb, admin PRODUCT/COMPANY link columns, app-download slot, socials,
     copyright + legal row via SiteChrome) — reskinned in the solar-flare
     amber/crimson-on-navy sports-broadcast persona already established on
     this theme's header/bottom-nav/login/landing/page surfaces.

     Section-boundary rule (owner request): a full-width DIAGONAL SLANT cut
     across the ENTIRE top edge (a jersey-stripe-style angled edge), never a
     centred notch (midnight-signal) or rounded corners (neon-vertex) — so
     no two themes share the same divider shape. The slant is a plain
     `clip-path` polygon from one top corner down to the other — the "full"
     variant has enough top padding (pt-16) to clear the 40px cut, but the
     slim login-page variant jumps straight to the copyright row with far
     less headroom, so the diagonal would slice through that text; slim
     drops the clip for a flat accent top border instead. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="solar-flare" @class([
    'relative isolate mt-10 overflow-hidden bg-navy text-slate-300',
    '[clip-path:polygon(0_40px,100%_0,100%_100%,0_100%)]' => $variant === 'full',
    'border-t-2 border-accent' => $variant !== 'full',
])>
    {{-- Diagonal jersey stripes, same recurring motif as header/bottom-nav. --}}
    <div class="pointer-events-none absolute inset-0 opacity-[0.10]" aria-hidden="true"
         style="background-image: repeating-linear-gradient(-45deg, rgb(var(--brand-primary)) 0 3px, transparent 3px 30px);"></div>
    <div class="pointer-events-none absolute -left-24 top-0 h-64 w-64 rounded-full bg-primary/15 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-16 bottom-0 h-56 w-56 rounded-full bg-accent/15 blur-3xl" aria-hidden="true"></div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-16 md:grid-cols-4">
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
                    <p class="mb-3 flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-[0.2em] text-primary">
                        <span class="h-2 w-2 shrink-0 bg-accent [clip-path:polygon(20%_0,100%_0,80%_100%,0_100%)]" aria-hidden="true"></span>
                        {{ $col['heading'] }}
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
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-primary to-accent px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-accent/30 transition hover:opacity-90 [clip-path:polygon(4%_0,100%_0,96%_100%,0_100%)]">
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
                       class="flex h-9 w-9 items-center justify-center rounded-full border border-primary/20 bg-primary/5 text-slate-300 transition hover:bg-primary/15 hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <span>&copy; {{ date('Y') }} {{ $brand }}. A product of <span class="text-slate-300">Supreme Ideas Agency</span>. All rights reserved.</span>
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
