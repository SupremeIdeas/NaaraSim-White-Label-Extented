{{-- Swappable FOOTER — "aurora-shift" style family (Theme Batch 3,
     2026-09-07). Persona: "Indigo Current" — deep indigo-violet fintech
     energy, electric-blue highlights, near-black gradient. Same functional
     content as components.site-footer (brand blurb, admin PRODUCT/COMPANY
     link columns, app-download slot, socials, copyright + legal row via
     SiteChrome) — reskinned in this theme's dark-terminal persona.

     Section-boundary rule (owner request): NEITHER neon-vertex's smooth
     rounded-top-corner sheet, NOR midnight-signal's centred triangular
     notch, NOR paperwhite's double hairline, NOR origin-bold's thick
     colour-block border, NOR noir-reserve's single-rounded-corner asymmetry
     — a full-width SMOOTH SINE-WAVE top edge (an SVG path, this persona's
     own "continuous current" motif, distinct from every clip-path/border/
     radius treatment used elsewhere on the platform). The same wave motif
     recurs at the top of this theme's landing/about/how-it-works/contact
     page sections for persona consistency. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="aurora-shift" class="relative isolate mt-14 bg-navy text-slate-300">
    {{-- The sine-wave divider — this persona's own boundary shape. --}}
    <svg class="absolute inset-x-0 bottom-full block h-8 w-full text-navy sm:h-12" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
        <path fill="currentColor" d="M0,30 C240,58 480,2 720,26 C960,50 1200,6 1440,28 L1440,60 L0,60 Z" />
    </svg>

    {{-- Quiet dot-grid + glow backdrop, echoing the login terminal panel. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 opacity-[0.25]" style="background-image: radial-gradient(rgb(var(--brand-accent) / 0.35) 1px, transparent 1px); background-size: 24px 24px;"></div>
        <span class="absolute -left-16 bottom-0 h-64 w-64 rounded-full bg-primary/20 blur-3xl"></span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-6 pb-14 pt-14 sm:px-8 sm:pt-16 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" theme="dark" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
                <p class="mt-5 inline-flex items-center gap-1.5 rounded-[0.625rem] border border-accent/30 bg-accent/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-accent">
                    <x-icon name="trending-up" class="h-3 w-3" /> Rates live-priced, 24/7
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-4 font-display text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $col['heading'] }}</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="text-slate-400 transition hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="relative z-10 border-t border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-6 py-5 sm:px-8">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-[0.625rem] bg-gradient-to-r from-primary to-accent px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary/30 transition hover:opacity-90">
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
                       class="flex h-9 w-9 items-center justify-center rounded-[0.625rem] border border-white/10 bg-white/5 text-slate-300 transition hover:border-accent/40 hover:text-accent">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-5 text-xs text-slate-500 sm:flex-row sm:px-8">
            <x-footer-credit link-class="text-slate-300" />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
