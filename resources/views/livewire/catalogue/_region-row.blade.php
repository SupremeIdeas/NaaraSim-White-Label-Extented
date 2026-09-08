{{-- One region as a horizontal list row (reference "Regions" layout): a small
     square map thumbnail on the left, name + teaser/count, chevron on the right.
     Expects: $t (grid region row), $fmt. --}}
@php($teaser = $t['from_usd'] !== null ? $fmt((float) $t['from_usd']) : null)
<button type="button" wire:click="openRegion('{{ $t['slug'] }}')"
        class="group flex items-center gap-3 rounded-2xl border border-slate-200 nx-glass-tile p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)]">
    <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 dark:bg-[var(--brand-card-inner-dark)]">
        @if (! empty($t['icon']))
            <img src="{{ $t['icon'] }}" alt="{{ $t['label'] }}" loading="lazy" class="h-full w-full object-cover">
        @else
            <x-icon name="globe" class="h-7 w-7 text-primary/70" gradient />
        @endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-base font-bold text-slate-900 dark:text-slate-100">{{ $t['label'] }}</p>
        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
            @if ($teaser)from {{ $teaser['usd'] }} · @endif{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('plan', $t['count']) }}
        </p>
    </div>
    <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-slate-300 group-hover:text-primary dark:text-slate-500" />
</button>
