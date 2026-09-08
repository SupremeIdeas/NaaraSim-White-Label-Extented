@php
    /** FAQ accordion (Section Builder §2). Native <details> so it works with no JS. */
    $c = $config ?? [];
    $items = array_values(array_filter((array) ($c['items'] ?? []), fn ($x) => is_array($x) && ($x['q'] ?? '') !== ''));
    $bordered = ($c['style'] ?? 'bordered') === 'bordered';
@endphp

@if ($items)
    <section class="nx-sec-faq py-14 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            @if (! empty($c['heading']))
                <h2 class="mb-8 text-center text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $c['heading'] }}</h2>
            @endif
            <div class="space-y-3">
                @foreach ($items as $item)
                    <details class="group {{ $bordered ? 'rounded-2xl border border-slate-200/70 bg-white px-5 dark:border-white/10 dark:bg-slate-900/60' : 'border-b border-slate-200 dark:border-white/10' }}">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-left font-semibold text-slate-900 dark:text-white">
                            {{ $item['q'] }}
                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" />
                        </summary>
                        <div class="pb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $item['a'] ?? '' }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
