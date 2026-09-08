{{-- One plan as a clean horizontal list row (reference "Select Package" layout):
     data + validity on the left, price on the right; the whole row opens the
     plan-detail view. Expects: $plan, $fmt. --}}
@php($price = $fmt((float) $plan->final_retail_usd))
<div wire:key="plan-{{ $plan->id }}" wire:click="openPlan({{ $plan->id }})" role="button" tabindex="0"
     class="group flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-slate-200 nx-glass-tile p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)]">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ $plan->data_mb ? rtrim(rtrim(number_format($plan->data_mb / 1024, 1), '0'), '.').' GB' : 'Unlimited' }}
            </span>
            <span class="text-sm text-slate-500 dark:text-slate-400">· {{ $plan->validity_days ? $plan->validity_days.' days' : 'flexible' }}</span>
            @if ($plan->is_featured)
                <span class="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">
                    <x-icon name="zap" class="h-3 w-3" /> Popular
                </span>
            @endif
        </div>
        <div class="mt-1 flex items-center gap-1.5">
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $plan->name }}</p>
            @if ($plan->display_tooltip)
                <span x-data="{ open: false }" class="relative" @click.stop>
                    <button type="button" @click="open = !open" @click.outside="open = false" aria-label="What is this plan?"
                            class="flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:text-primary">
                        <x-icon name="info" class="h-3.5 w-3.5" />
                    </button>
                    <span x-show="open" x-cloak x-transition
                          class="absolute left-0 top-6 z-10 w-56 rounded-lg border border-slate-200 bg-white p-3 text-left text-xs leading-relaxed text-slate-600 shadow-lg dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] dark:text-slate-300">
                        {{ $plan->display_tooltip }}
                    </span>
                </span>
            @endif
        </div>
    </div>
    <div class="shrink-0 text-right">
        <div class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $price['usd'] }}</div>
        @if ($price['local'])
            <div class="text-xs text-slate-400 dark:text-slate-500">≈ {{ $price['local'] }}</div>
        @endif
    </div>
</div>
