@php
    /** Carousel (Section Builder §2 / Blog §3). Horizontal scroll-snap row with a
     *  soft swell on the card nearest centre (CSS scroll-snap + a scale hover;
     *  reduced-motion safe). */
    $c = $config ?? [];
    $cards = array_values(array_filter((array) ($c['cards'] ?? []), fn ($x) => is_array($x) && ($x['title'] ?? '') !== ''));
    $seeAll = \App\Support\PageSections::target($c['see_all_target'] ?? '');
@endphp

@if ($cards)
    <section class="nx-sec-carousel py-12 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white sm:text-2xl">{{ $c['heading'] ?? '' }}</h2>
                @if (! empty($c['see_all_label']) && $seeAll)
                    <a href="{{ $seeAll }}" class="text-sm font-semibold text-primary hover:underline dark:text-teal-300">{{ $c['see_all_label'] }}</a>
                @endif
            </div>
            <div class="nx-carousel-row flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($cards as $card)
                    @php $href = \App\Support\PageSections::target($card['target'] ?? ''); @endphp
                    <a @if ($href) href="{{ $href }}" @endif
                        class="nx-carousel-card group relative aspect-[3/4] w-56 shrink-0 snap-center overflow-hidden rounded-3xl bg-slate-800 shadow-sm transition duration-300 hover:scale-[1.03]">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-primary to-navy"></div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-black/40"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4">
                            @if (! empty($card['subtitle']))<span class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ $card['subtitle'] }}</span>@endif
                            <h3 class="mt-0.5 text-base font-bold leading-tight text-white">{{ $card['title'] }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
