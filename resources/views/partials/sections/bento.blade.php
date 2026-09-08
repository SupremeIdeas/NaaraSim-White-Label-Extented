@php
    /** Bento grid section (Section Builder §2 / Homepage §1). Varied-weight cards;
     *  graceful no-image fallback → clean icon/text card, never a broken slot. */
    $c = $config ?? [];
    $cards = array_values(array_filter((array) ($c['cards'] ?? []), fn ($x) => is_array($x) && (($x['title'] ?? '') !== '' || ($x['body'] ?? '') !== '')));
    $layout = $c['layout'] ?? 'rhythm';
    $count = count($cards);
    // Rhythm: first card spans 2 cols on desktop, rest single — deliberate weight.
    $spanFor = function (int $i) use ($layout, $count) {
        if ($layout === 'uniform') return '';
        if ($layout === 'featured') return $i === 0 ? 'sm:col-span-2 sm:row-span-2' : '';
        // rhythm 2/1/2/1
        return in_array($i % 4, [0, 3], true) ? 'sm:col-span-2' : '';
    };
@endphp

@if ($count)
    <section class="nx-sec-bento py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            @if (! empty($c['heading']))
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $c['heading'] }}</h2>
                    @if (! empty($c['subheading']))<p class="mx-auto mt-2 max-w-2xl text-slate-600 dark:text-slate-300">{{ $c['subheading'] }}</p>@endif
                </div>
            @endif

            <div class="grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $i => $card)
                    @php $href = \App\Support\PageSections::target($card['cta_target'] ?? ''); @endphp
                    <a @if ($href) href="{{ $href }}" @endif
                        class="nx-bento group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200/70 bg-white p-6 transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-slate-900/60 {{ $spanFor($i) }}">
                        @if (! empty($card['badge']))
                            <span class="nx-badge absolute right-4 top-4 rounded-full px-2.5 py-0.5 text-[11px] font-bold text-white">{{ $card['badge'] }}</span>
                        @endif
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" class="mb-4 h-32 w-full rounded-2xl object-cover">
                        @elseif (! empty($card['icon']))
                            <span class="mb-4 inline-flex w-fit rounded-2xl bg-primary/10 p-3 text-primary dark:text-teal-300"><x-icon name="{{ $card['icon'] }}" class="h-6 w-6" /></span>
                        @endif
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $card['title'] ?? '' }}</h3>
                        @if (! empty($card['body']))<p class="mt-1.5 flex-1 text-sm text-slate-600 dark:text-slate-300">{{ $card['body'] }}</p>@endif
                        @if (! empty($card['cta_label']))
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary transition group-hover:gap-2 dark:text-teal-300">
                                {{ $card['cta_label'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
