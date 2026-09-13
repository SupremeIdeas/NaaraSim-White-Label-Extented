{{-- Swappable FOOTER — "aries-contrast" style family (Theme visual rebuild,
     Batch 2, 2026-09-07). Same functional content as components.site-footer
     (brand blurb, admin PRODUCT/COMPANY link columns, app-download slot,
     socials, copyright + legal row via SiteChrome) — reskinned in the
     aries-contrast persona already established on this theme's header/
     bottom-nav/login: solid black/white, zero rounding, a small live-status
     pulse dot by the wordmark, gold as the only decoration.

     Section-boundary rule (owner request): a thick 4px solid gold rule
     across the very top edge — no rounded "sheet" corner (neon-vertex) and
     no angular clip-path notch (midnight-signal). A brutalist persona
     rejects both soft curves and cut notches, so a flat, hard gold line is
     THIS theme's deliberate divider treatment. The link columns are
     separated by thin gold rules (a scoreboard "cell" feel) rather than
     plain gaps; the secondary rows below (app-download / socials / legal)
     keep neutral thin dividers, same restraint the header uses (one bold
     accent rule, everything else neutral) so gold stays a signal, not
     wallpaper. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="aries-contrast" class="relative border-t-4 border-accent bg-white text-slate-600 dark:bg-black dark:text-slate-400">
    @if ($variant === 'full')
        <div class="mx-auto grid max-w-6xl divide-y-2 divide-accent/40 px-4 py-14 dark:divide-accent/30 md:grid-cols-4 md:gap-10 md:divide-x-2 md:divide-y-0">
            <div class="pb-8 md:col-span-2 md:pb-0 md:pr-10">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                    <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-accent"></span>
                    </span>
                    <x-brand-logo variant="family" size="lg" />
                </a>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div class="py-8 md:px-8 md:py-0">
                    <p class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-slate-900 dark:text-white">{{ $col['heading'] }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="transition hover:text-accent">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1). --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="border-t-2 border-slate-200 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-4 py-5">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 border-2 border-slate-900 px-5 py-2.5 text-sm font-bold uppercase tracking-wide text-slate-900 transition hover:border-accent hover:text-accent dark:border-white dark:text-white">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has configured. --}}
    @if (! empty($socials))
        <div class="border-t-2 border-slate-200 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-4">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-9 w-9 items-center justify-center border-2 border-slate-200 text-slate-500 transition hover:border-accent hover:text-accent dark:border-white/10 dark:text-slate-400">
                        <x-service-icon :slug="$s['icon']" class="h-4 w-4" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="border-t-2 border-slate-200 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
            <x-footer-credit link-class="font-semibold text-slate-900 dark:text-white" />
            <span class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($legal as $link)
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-accent">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
