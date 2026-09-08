@props([
    'wire' => null,          // Livewire property to live-bind (debounced)
    'placeholder' => 'Search…',
    'debounce' => '300ms',
    'event' => 'search',     // pure-Alpine: debounced event name dispatched with the term
    'label' => 'Search',
])

<div {{ $attributes->merge(['class' => 'relative']) }} role="search">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
        <x-icon name="search" class="h-4 w-4" />
    </span>

    @if ($wire)
        <input type="search"
               wire:model.live.debounce.{{ $debounce }}="{{ $wire }}"
               aria-label="{{ $label }}"
               placeholder="{{ $placeholder }}"
               class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-primary dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
    @else
        <input type="search"
               x-data="{ term: '' }"
               x-model="term"
               x-on:input.debounce.{{ $debounce }}="$dispatch('{{ $event }}', { term })"
               aria-label="{{ $label }}"
               placeholder="{{ $placeholder }}"
               class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-primary dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
    @endif
</div>
