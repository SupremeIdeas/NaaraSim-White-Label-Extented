{{-- Swappable FOOTER — "neon-vertex" style family (Theme visual rebuild,
     owner request 2026-09-07: "please all themes too should have unique
     footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned in the neon-vertex ultraviolet/
     hot-pink persona already established on this theme's landing/about/
     how-it-works/contact pages: bg-white/dark:bg-navy (not navy-always like
     the default), font-display headings, gradient blobs, rounded-3xl cards.

     Section-boundary rule (owner request): a `rounded-t-[2.5rem]` top edge
     — the footer reads as a soft rounded "sheet" laid over whatever section
     precedes it on any page, never a flush straight line. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="neon-vertex" class="relative isolate mt-6 overflow-hidden rounded-t-[2.5rem] border-t border-primary/15 bg-white text-slate-600 shadow-[0_-24px_60px_-32px_rgba(15,23,42,0.18)] dark:border-accent/20 dark:bg-navy dark:text-slate-300">
    {{-- Ultraviolet/hot-pink glow, echoing the landing hero's blob backdrop. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <span class="absolute -top-24 left-1/2 h-72 w-[38rem] -translate-x-1/2 rounded-full bg-gradient-to-r from-primary/25 via-accent/20 to-transparent blur-3xl"></span>
        <span class="absolute -bottom-16 -right-16 h-64 w-64 rounded-full bg-accent/10 blur-3xl"></span>
    </div>

    @if ($variant === 'full')
        <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 pb-14 pt-16 md:grid-cols-4">
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
                    <p class="mb-3 font-display text-xs font-semibold uppercase tracking-widest text-primary dark:text-accent">{{ $col['heading'] }}</p>
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
            <div class="relative z-10 border-t border-slate-200/70 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-primary to-accent px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/30 transition hover:opacity-90">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="relative z-10 border-t border-slate-200/70 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/5 text-slate-500 transition hover:bg-primary/10 hover:text-primary dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="relative z-10 border-t border-slate-200/70 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-400 dark:text-slate-500 sm:flex-row">
            <x-footer-credit link-class="text-slate-600 dark:text-slate-300" />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-primary dark:hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
