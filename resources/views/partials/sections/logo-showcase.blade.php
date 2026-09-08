@php
    /** Logo showcase (Section Builder §2). Auto-scrolling marquee of logos; the set
     *  is duplicated for a seamless loop. Frozen under prefers-reduced-motion. */
    $c = $config ?? [];
    $logos = array_values(array_filter((array) ($c['logos'] ?? []), fn ($x) => is_array($x) && ($x['src'] ?? '') !== ''));
@endphp

@if ($logos)
    <section class="nx-sec-logos overflow-hidden py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            @if (! empty($c['heading']))
                <p class="mb-6 text-center text-xs font-semibold uppercase tracking-widest text-slate-400">{{ $c['heading'] }}</p>
            @endif
            <div class="nx-logo-marquee relative flex overflow-hidden">
                <div class="nx-logo-track flex shrink-0 items-center gap-12 pr-12">
                    @foreach (array_merge($logos, $logos) as $logo)
                        <img src="{{ $logo['src'] }}" alt="{{ $logo['alt'] ?? '' }}" loading="lazy"
                             class="h-8 w-auto opacity-60 grayscale transition hover:opacity-100 hover:grayscale-0 dark:invert dark:opacity-70">
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @once
        <style>
            .nx-logo-track { animation: nxLogoScroll 30s linear infinite; }
            @keyframes nxLogoScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
            @media (prefers-reduced-motion: reduce) { .nx-logo-track { animation: none; } }
        </style>
    @endonce
@endif
