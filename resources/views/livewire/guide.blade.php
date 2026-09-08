@php
    $sections = collect($guide->sections ?? [])->filter(fn ($s) => is_array($s) && ($s['heading'] ?? '') !== '');
@endphp
<div class="mx-auto max-w-3xl">
    {{-- Header --}}
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-primary dark:text-teal-300">{{ \App\Support\UserGuides::label($audience) }}</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $guide->title }}</h1>
        @if ($guide->intro)<p class="mt-2 text-slate-600 dark:text-slate-300">{{ $guide->intro }}</p>@endif

        {{-- Quick switch between guides --}}
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (\App\Support\UserGuides::AUDIENCES as $a)
                <a href="{{ route('guide', ['audience' => $a]) }}" wire:navigate
                   @class([
                       'rounded-full px-3 py-1.5 text-xs font-medium transition',
                       'bg-primary text-white' => $audience === $a,
                       'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' => $audience !== $a,
                   ])>{{ \App\Support\UserGuides::label($a) }}</a>
            @endforeach
        </div>
    </div>

    {{-- Sections --}}
    <div class="space-y-6">
        @foreach ($sections as $section)
            <div class="rounded-2xl border border-slate-200/70 nx-glass-tile p-5 dark:border-white/10">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $section['heading'] }}</h2>
                @if (! empty($section['image']))
                    <img src="{{ $section['image'] }}" alt="" loading="lazy" class="my-3 w-full rounded-xl object-cover">
                @endif
                @if (! empty($section['body']))
                    <div class="nx-prose mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{!! \App\Support\HtmlSanitizer::clean($section['body']) !!}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Agreement / policy --}}
    @if ($guide->agreement)
        <div class="mt-8 rounded-2xl border border-primary/20 bg-primary/5 p-5 dark:border-teal-300/20 dark:bg-primary/10">
            <h2 class="mb-2 flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"><x-icon name="shield" class="h-5 w-5 text-primary dark:text-teal-300" /> Your agreement &amp; policy</h2>
            <div class="nx-prose text-sm leading-relaxed text-slate-700 dark:text-slate-200">{!! \App\Support\HtmlSanitizer::clean($guide->agreement) !!}</div>
        </div>
    @endif
</div>
