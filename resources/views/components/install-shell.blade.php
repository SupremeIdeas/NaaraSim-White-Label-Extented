@props(['step' => 1])
@php
    $steps = ['Welcome', 'Server Requirements', 'Database Setup', 'Done'];
@endphp
<div class="mx-auto max-w-3xl px-4 py-10">
    {{-- Step chevrons (matches the referenced installer pattern) --}}
    <ol class="mb-10 flex items-center justify-between gap-1 text-sm">
        @foreach ($steps as $i => $label)
            @php $n = $i + 1; @endphp
            <li class="flex items-center gap-2">
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold',
                    'border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100' => $n === $step,
                    'border-green-500 bg-green-500 text-white' => $n < $step,
                    'border-slate-300 text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500' => $n > $step,
                ])>
                    @if ($n < $step) <x-icon name="check" class="h-4 w-4" /> @else {{ $n }} @endif
                </span>
                <span @class([
                    'hidden font-semibold sm:inline',
                    'text-slate-900 dark:text-slate-100' => $n === $step,
                    'text-slate-400 dark:text-slate-500' => $n !== $step,
                ])>{{ $label }}</span>
                @unless ($loop->last)
                    <x-icon name="chevron-right" class="ml-1 hidden h-4 w-4 text-slate-300 dark:text-[#2D4060] sm:inline" />
                @endunless
            </li>
        @endforeach
    </ol>

    <div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        {{ $slot }}
    </div>
</div>
