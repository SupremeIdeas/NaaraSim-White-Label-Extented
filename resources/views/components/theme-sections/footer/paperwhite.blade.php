{{-- Swappable FOOTER — "paperwhite" style family (Theme visual rebuild,
     owner request 2026-09-07: "please all themes too should have unique
     footer too... not always we get a straight line footer"). Same
     functional content as components.site-footer (brand blurb, admin
     PRODUCT/COMPANY link columns, app-download slot, socials, copyright +
     legal row via SiteChrome) — reskinned in the paperwhite persona already
     established on this theme's header/bottom-nav/login: NO glass, blur,
     shadow, or gradient anywhere — just hairline rules on a paper-white
     ground, ink-black type, generous whitespace.

     Section-boundary rule (owner request): neither neon-vertex's rounded-
     top "sheet" nor midnight-signal's notch — a DOUBLE HAIRLINE (two 1px
     rules with a small gap) sits at the very top of the footer, an
     understated, deliberate variation befitting this theme's "restraint as
     the whole point" persona. --}}
@php
    $variant = $variant ?? 'full';
    $columns = \App\Support\SiteChrome::footerColumns();
    $legal = \App\Support\SiteChrome::footerLegal();
    $brand = \App\Support\BrandSettings::name();
    $ext = fn ($url) => preg_match('#^https?://#i', $url) === 1;
    $socials = \App\Support\SocialLinks::forFooter();
@endphp

<footer data-footer-style="paperwhite" class="relative mt-6 bg-[#F8F9FA] text-slate-600 dark:bg-navy dark:text-slate-400">
    {{-- The double hairline divider: two thin rules with a small gap. --}}
    <div class="border-t border-slate-200 dark:border-white/10"></div>
    <div class="mt-1 border-t border-slate-200 dark:border-white/10"></div>

    @if ($variant === 'full')
        <div class="mx-auto grid max-w-6xl gap-10 px-5 pb-12 pt-16 sm:px-6 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand-logo variant="family" size="lg" />
                <p class="mt-5 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $brand }} — Stay Connected. No Borders. No Swaps. Premium eSIM
                    connectivity and phone numbers for African travellers and global
                    professionals. 190+ countries, instant activation, no roaming surprises.
                </p>
            </div>
            @foreach ($columns as $col)
                <div>
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">{{ $col['heading'] }}</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($col['links'] as $link)
                            <li>
                                <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                                   class="text-slate-600 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Admin-assignable "Download the app" footer slot (App Export §1) —
             a quiet underlined text link, not a filled pill, to match this
             theme's one-CTA-as-a-link discipline. --}}
        @if (\App\Support\AppExport::placementActive('footer'))
            <div class="border-t border-slate-200 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-center px-5 py-6 sm:px-6">
                    <a href="{{ route('download') }}"
                        class="inline-flex items-center gap-1.5 border-b border-slate-400 pb-0.5 text-sm font-medium text-slate-800 transition hover:border-slate-900 dark:border-slate-500 dark:text-slate-200 dark:hover:border-white">
                        {{ \App\Support\AppExport::placementLabel('footer') }} <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            </div>
        @endif
    @endif

    {{-- Social links (owner request): shown only for platforms the admin has
         configured — quiet hairline-bordered circles, no filled background. --}}
    @if (! empty($socials))
        <div class="border-t border-slate-200 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-5 py-5 sm:px-6">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}"
                       class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-slate-500 transition hover:border-slate-900 hover:text-slate-900 dark:border-white/15 dark:text-slate-400 dark:hover:border-white dark:hover:text-white">
                        <x-service-icon :slug="$s['icon']" class="h-3.5 w-3.5" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bottom bar — legal links read as a quiet masthead credits line,
         separated by middot characters rather than loose spacing. --}}
    <div class="border-t border-slate-200 dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-5 py-6 text-xs text-slate-400 dark:text-slate-500 sm:flex-row sm:px-6">
            <x-footer-credit link-class="text-slate-600 dark:text-slate-300" />
            <span class="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1">
                @foreach ($legal as $i => $link)
                    @if ($i > 0)<span aria-hidden="true">&middot;</span>@endif
                    <a href="{{ $link['url'] }}" @if ($ext($link['url'])) target="_blank" rel="noopener" @endif
                       class="transition hover:text-slate-900 dark:hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
