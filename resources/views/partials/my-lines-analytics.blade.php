{{-- "My Analytics" panel (Connectivity Analytics blueprint Part A §2.4/2.7) —
     plan mix, purchase cadence, spend by public Model, and deposit history.
     Collapsed by default: Chart.js is only fetched the first time a user
     actually opens this panel, via window.NaaraLinesAnalytics.mountAll().
     Expects $planMix, $purchaseCadence, $spendBreakdown, $topUpHistory from
     MyLines::analyticsPanel(). Never shows a provider name or a cost. --}}
@php
    $planMixChart = ['labels' => collect($planMix)->pluck('label'), 'values' => collect($planMix)->pluck('count')];
    $cadenceChart = ['labels' => collect($purchaseCadence)->pluck('week'), 'values' => collect($purchaseCadence)->pluck('count')];
    $spendChart = ['labels' => collect($spendBreakdown)->pluck('label'), 'values' => collect($spendBreakdown)->pluck('total')];
    $topUpChart = ['labels' => collect($topUpHistory)->pluck('date'), 'values' => collect($topUpHistory)->pluck('total')];
@endphp
<div x-data="{ open: false, mounted: false }" class="mt-8 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
    <button type="button"
            @click="open = ! open; if (open && ! mounted) { mounted = true; $nextTick(() => window.NaaraLinesAnalytics?.mountAll($el.closest('[x-data]'))) }"
            class="flex w-full items-center justify-between text-left">
        <span class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <x-icon name="signal" class="h-4 w-4" /> My Analytics
        </span>
        <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition-transform" ::class="open && 'rotate-90'" />
    </button>

    <div x-show="open" x-cloak class="mt-5 grid gap-6 sm:grid-cols-2">
        <div>
            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Where you connect</p>
            @if (count($planMix))
                <div class="h-48"><canvas data-chart-type="donut" data-chart='@json($planMixChart)'></canvas></div>
            @else
                <p class="text-xs text-slate-400 dark:text-slate-500">Buy an eSIM to see this.</p>
            @endif
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">How often you buy (last 90 days)</p>
            @if (count($purchaseCadence))
                <div class="h-48"><canvas data-chart-type="bar" data-chart='@json($cadenceChart)'></canvas></div>
            @else
                <p class="text-xs text-slate-400 dark:text-slate-500">No purchases in the last 90 days yet.</p>
            @endif
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Spend by category (last 30 days)</p>
            @if (count($spendBreakdown))
                <div class="h-48"><canvas data-chart-type="donut" data-chart='@json($spendChart)'></canvas></div>
            @else
                <p class="text-xs text-slate-400 dark:text-slate-500">No spend in the last 30 days yet.</p>
            @endif
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Deposits (last 90 days)</p>
            @if (count($topUpHistory))
                <div class="h-48"><canvas data-chart-type="bar" data-chart='@json($topUpChart)'></canvas></div>
            @else
                <p class="text-xs text-slate-400 dark:text-slate-500">No wallet top-ups in the last 90 days yet.</p>
            @endif
        </div>
    </div>
</div>
