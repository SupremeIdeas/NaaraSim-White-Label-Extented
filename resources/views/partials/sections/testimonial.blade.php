@php
    /** Testimonials (Section Builder §2). Grid of quote cards; optional photo + stars. */
    $c = $config ?? [];
    $items = array_values(array_filter((array) ($c['items'] ?? []), fn ($x) => is_array($x) && ($x['quote'] ?? '') !== ''));
@endphp

@if ($items)
    <section class="nx-sec-testimonial py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            @if (! empty($c['heading']))
                <h2 class="mb-8 text-center text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $c['heading'] }}</h2>
            @endif
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <figure class="flex flex-col rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-white/10 dark:bg-slate-900/60">
                        @php $rating = (int) ($item['rating'] ?? 0); @endphp
                        @if ($rating > 0)
                            <div class="mb-3 flex gap-0.5 text-accent-dark dark:text-accent">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-icon name="star" class="h-4 w-4 {{ $i < $rating ? 'text-accent-dark dark:text-accent' : 'text-slate-300 dark:text-white/15' }}" />
                                @endfor
                            </div>
                        @endif
                        <blockquote class="flex-1 text-slate-700 dark:text-slate-200">“{{ $item['quote'] }}”</blockquote>
                        <figcaption class="mt-4 flex items-center gap-3">
                            @if (! empty($item['photo']))
                                <img src="{{ $item['photo'] }}" alt="" class="h-9 w-9 rounded-full object-cover">
                            @endif
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $item['name'] ?? '' }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
