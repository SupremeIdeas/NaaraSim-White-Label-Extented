{{-- One country as a horizontal list row (reference "Countries" layout):
     EXPLORE label + name + teaser on the left, the scaled 3D model (or flag
     fallback) on the right, then a chevron. Expects: $t (grid row), $fmt. --}}
@php($teaser = $t['from_usd'] !== null ? $fmt((float) $t['from_usd']) : null)
<button type="button" wire:click="openCountry('{{ $t['code'] }}')"
        class="group flex items-center gap-3 rounded-2xl border border-slate-200 nx-glass-tile p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)]">
    <div class="min-w-0 flex-1 pl-1">
        <p class="text-[11px] font-bold uppercase tracking-wider text-primary dark:text-teal-300">Explore</p>
        <p class="truncate text-base font-bold text-slate-900 dark:text-slate-100">{{ $t['name'] }}</p>
        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
            @if ($teaser)from {{ $teaser['usd'] }} · @endif{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('plan', $t['count']) }}
        </p>
    </div>
    <div class="flex shrink-0 items-center gap-1">
        <span class="flex h-16 w-24 items-center justify-center">
            @if (! empty($t['icon']))
                <img src="{{ $t['icon'] }}" alt="{{ $t['name'] }}" loading="lazy"
                     class="h-16 w-24 object-contain transition-transform duration-300 group-hover:scale-105">
            @else
                <x-country-flag :country="$t['code']" class="h-10 w-14 rounded shadow-sm" />
            @endif
        </span>
        <x-icon name="chevron-right" class="h-5 w-5 text-slate-300 group-hover:text-primary dark:text-slate-500" />
    </div>
</button>
