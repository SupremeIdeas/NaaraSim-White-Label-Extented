{{-- Swappable FOOTER — "fintra-clean" style family ("Ledger" persona, Theme
     visual rebuild, 2026-09-07). Same functional content as
     components.site-footer (brand blurb, admin PRODUCT/COMPANY link
     columns, app-download slot, socials, copyright + legal row via
     SiteChrome) — reskinned in the fintra-clean persona already established
     on this theme's header/bottom-nav/login: slate-blue and champagne-gold,
     only lightly rounded, dense tabular numbers, rows separated by thin
     rule lines like a spreadsheet.

     Section-boundary rule (owner request): a TEAR-OFF STATEMENT
     PERFORATION — a row of small punched dots followed by a dashed rule,
     exactly like tearing a paper receipt off a register roll. Distinct
     from every divider shape already used on the platform: neon-vertex's
     rounded-t "sheet", midnight-signal's clip-path notch, paperwhite's
     double hairline, noir-reserve's asymmetric single rounded corner,
     origin-bold/aries-contrast's thick colour-block border, and
     solar-flare's diagonal clip-path slant. A tear-off perforation is the
     one motif genuinely native to this persona: you "tear off" the
     statement at the footer, the same way a bank receipt ends. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="fintra-clean" class="relative mt-6 bg-white text-slate-600 dark:bg-navy dark:text-slate-400">
    {{-- Perforation holes, then the dashed tear line. --}}
    <div class="h-3 w-full" aria-hidden="true"
         style="background-image: radial-gradient(circle, rgb(148 163 184 / 0.6) 0 2px, transparent 2px); background-size: 14px 12px; background-position: center;"></div>
    <div class="border-t-2 border-dashed border-slate-300 dark:border-white/20"></div>

    @if ($variant === 'full')
        <div class="mx-auto grid max-w-6xl gap-10 px-5 pb-10 pt-12 sm:px-6 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. One
                    reconciled ledger for eSIM data and phone numbers across
                    190+ countries — the rate you're quoted is the rate you
                    pay, itemised, every time.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-4 flex items-center gap-1.5 border-b border-slate-200 pb-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-400 dark:border-white/10 dark:text-slate-500">
                        <x-icon name="hash" class="h-3 w-3" /> {{ $col['heading'] }}
                    </p>
                    <ul class="divide-y divide-slate-100 text-sm dark:divide-white/5">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="block py-2 text-slate-600 transition hover:text-primary dark:text-slate-400 dark:hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1) —
             rendered as a ledger action row: a lightly-rounded control-radius
             button, never a pill. --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="border-t border-slate-200 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-5 py-6 sm:px-6">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark">
                        <x-icon name="download" class="h-4 w-4" /> {{ \App\Support\AppExport::placementLabel('footer') }}
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links — quiet, lightly-rounded control-radius squares (not
         circles), matching this persona's near-sharp radius tokens. --}}
    @if (! empty($socials))
        <div class="border-t border-slate-200 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-5 py-5 sm:px-6">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:border-primary hover:text-primary dark:border-white/15 dark:text-slate-400 dark:hover:border-white dark:hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-3.5 w-3.5" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bottom bar — a closing "statement" line with tabular-nums, then the
         legal links as a plain row separated by thin rules. --}}
    <div class="border-t border-slate-200 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-5 py-6 text-xs text-slate-400 dark:text-slate-500 sm:flex-row sm:px-6">
            <span class="flex items-center gap-1.5">
                <x-footer-credit link-class="text-slate-600 dark:text-slate-300" year-class="font-mono tabular-nums" />
            </span>
            <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                @foreach ($legal as $i => $link)
                    @if ($i > 0)<span aria-hidden="true" class="text-slate-300 dark:text-slate-700">|</span>@endif
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-primary dark:hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
