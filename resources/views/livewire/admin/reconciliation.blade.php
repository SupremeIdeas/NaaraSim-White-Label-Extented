<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Financial reconciliation</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Money in vs. wallet credits vs. paid out vs. provider costs — built on the wallet ledger, so an unaccounted-for gap surfaces early.</p>
        </div>
        {{-- Period toggle --}}
        <div class="flex items-center gap-0.5 rounded-lg bg-slate-100 p-0.5 dark:bg-[#243352]">
            @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $d => $label)
                <button type="button" wire:click="setDays({{ $d }})"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-semibold transition',
                            'bg-white text-primary shadow-sm dark:bg-[#1A2840] dark:text-teal-300' => $days === $d,
                            'text-slate-500 hover:text-slate-700 dark:text-slate-400' => $days !== $d,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <p class="mb-4 text-xs text-slate-400 dark:text-slate-500">{{ $report['from'] }} → {{ $report['to'] }} · all figures USD unless labelled</p>

    {{-- Reconciliation signal — the headline: money the gateways report in vs.
         money the wallet ledger credited. A large gap = investigate. --}}
    @php($gap = $report['reconciliation_gap'])
    <div @class([
        'mb-6 rounded-2xl border p-5',
        'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-950/30' => abs($gap) < 0.01,
        'border-amber-300 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30' => abs($gap) >= 0.01,
    ])>
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">Reconciliation gap</p>
                <p @class([
                    'mt-1 text-3xl font-bold',
                    'text-green-700 dark:text-green-300' => abs($gap) < 0.01,
                    'text-amber-700 dark:text-amber-300' => abs($gap) >= 0.01,
                ])>${{ number_format($gap, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gateway money in (${{ number_format($report['total_in'], 2) }}) − wallet credits (${{ number_format($report['wallet_credits'], 2) }})</p>
            </div>
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold',
                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => abs($gap) < 0.01,
                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => abs($gap) >= 0.01,
            ])>
                <x-icon name="{{ abs($gap) < 0.01 ? 'check' : 'info' }}" class="h-4 w-4" />
                {{ abs($gap) < 0.01 ? 'Balanced' : 'Investigate' }}
            </span>
        </div>
        @if (abs($gap) >= 0.01)
            <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">A non-zero gap can mean a top-up webhook charged the customer but never credited the wallet (or a double credit). Check recent charges against wallet transactions.</p>
        @endif
    </div>

    {{-- KPI row --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Money in', $report['total_in'], 'Gateway charges'],
            ['Paid out', $report['paid_out'], 'Settled payouts'],
            ['Provider cost', $report['provider_cost'], 'Fulfilled orders'],
            ['Gross profit', $report['gross_profit'], 'Retail − cost'],
        ] as [$label, $value, $sub])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">${{ number_format($value, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Money in by gateway --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Money in by gateway</h2>
            @forelse ($report['in_by_gateway'] as $gateway => $amount)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="capitalize text-slate-600 dark:text-slate-300">{{ $gateway }}</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100">${{ number_format($amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No gateway charges in this period.</p>
            @endforelse
        </div>

        {{-- Wallet ledger by type --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Wallet ledger by type</h2>
            @forelse ($report['wallet_by_type'] as $type => $amount)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="capitalize text-slate-600 dark:text-slate-300">{{ $type }}</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100">${{ number_format($amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No wallet movements in this period.</p>
            @endforelse
        </div>
    </div>

    {{-- Outstanding liabilities (point-in-time) --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Outstanding liabilities <span class="font-normal text-slate-400">(right now, not the period)</span></h2>
        <p class="mb-3 text-xs text-slate-400">What the platform still owes — user wallet balances + payouts not yet settled.</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div>
                <p class="text-xs text-slate-400">Wallet balances (USD)</p>
                <p class="text-lg font-bold text-slate-900 dark:text-slate-100">${{ number_format($report['outstanding_usd'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Wallet balances (NGN, legacy)</p>
                <p class="text-lg font-bold text-slate-900 dark:text-slate-100">₦{{ number_format($report['outstanding_ngn'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Payouts owed</p>
                <p class="text-lg font-bold text-slate-900 dark:text-slate-100">${{ number_format($report['pending_out'], 2) }}</p>
            </div>
        </div>
    </div>
</div>
