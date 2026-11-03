{{-- Capabilities (CMS: home.capabilities): a big interactive list. Hover, focus or tap a row and its panel opens on the right (a stacked accordion on phones).
     Every row is editable: icon, title, line, bullets (one per line), button label and link, and a picture. --}}
@php
    $rows = [];
    foreach ([1, 2, 3, 4, 5] as $n) {
        if (trim((string) ($s["c{$n}_title"] ?? '')) === '') {
            continue;
        }
        // A card that points at a feature this licence does not include is not advertised.
        $u = strtolower((string) ($s["c{$n}_url"] ?? ''));
        if ((str_contains($u, 'gift-cards') && \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_GIFT_CARDS)) || (str_contains($u, '/brand') && \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT))) {
            continue;
        }
        $rows[] = [
            'icon' => $s["c{$n}_icon"] ?? 'sparkles', 'title' => $s["c{$n}_title"], 'text' => $s["c{$n}_text"] ?? '',
            'bullets' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($s["c{$n}_bullets"] ?? ''))))),
            'cta' => $s["c{$n}_cta"] ?? '', 'url' => $s["c{$n}_url"] ?? '', 'img' => \App\Support\SiteContent::imageUrl($s["c{$n}_image"] ?? ''),
        ];
    }
@endphp
<section class="mx-auto max-w-6xl px-4 py-24" data-bg="light" x-data="{ open: 0 }">
    <div class="grid gap-10 lg:grid-cols-[.9fr_1.4fr] lg:gap-16">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] ?? '' }}</p>
            <h2 data-reveal class="mt-4 text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-5xl dark:text-white">{{ $s['headline'] ?? '' }} <span class="nx-gradient-text">{{ $s['headline_accent'] ?? '' }}</span></h2>
            <p data-reveal class="mt-5 max-w-md leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['intro'] ?? '' }}</p>
        </div>

        <div class="nx-cap" role="list">
            @foreach ($rows as $i => $r)
                <div class="nx-cap__row" role="listitem" :class="{ 'is-open': open === {{ $i }} }" @mouseenter="if (window.matchMedia('(hover: hover)').matches) open = {{ $i }}">
                    <button type="button" class="nx-cap__head" @click="open = {{ $i }}" @focus="open = {{ $i }}" :aria-expanded="open === {{ $i }}" aria-controls="cap-{{ $i }}">
                        <span class="nx-cap__no">0{{ $i + 1 }}</span>
                        <span class="nx-cap__title">{{ $r['title'] }}</span>
                        <span class="nx-cap__icon"><x-glyph :name="$r['icon']" :size="40" tile /></span>
                    </button>
                    <div id="cap-{{ $i }}" class="nx-cap__panel" x-show="open === {{ $i }}" x-collapse.duration.300ms>
                        <div class="grid gap-5 pb-6 pt-1 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div>
                                <p class="max-w-md text-slate-600 dark:text-slate-300">{{ $r['text'] }}</p>
                                @if ($r['bullets'])
                                    <ul class="mt-4 space-y-2">
                                        @foreach ($r['bullets'] as $b)<li class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200"><x-glyph name="check" :size="16" class="mt-0.5 shrink-0" />{{ $b }}</li>@endforeach
                                    </ul>
                                @endif
                                @if ($r['cta'] && $r['url'])
                                    <a href="{{ str_starts_with($r['url'], 'http') || str_starts_with($r['url'], '/') ? $r['url'] : '/'.$r['url'] }}" class="nx-btn nx-btn--primary mt-5 !px-5 !py-2.5 !text-sm">{{ $r['cta'] }}</a>
                                @endif
                            </div>
                            @if ($r['img'])
                                <img src="{{ $r['img'] }}" alt="{{ $r['title'] }}" loading="lazy" class="h-36 w-full rounded-2xl object-cover sm:h-40 sm:w-56">
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
