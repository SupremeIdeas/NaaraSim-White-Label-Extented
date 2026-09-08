<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Partner earnings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Your share of the platform, paid to you every {{ $partner->payout_cadence === 'weekly' ? 'week' : 'month' }}.</p>
    </div>

    @if ($suspended)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">Your partner account is paused. Please contact support.</div>
    @endif

    {{-- Balance card (dollars only — the share % is never shown). --}}
    <div class="nx-aurora mb-5">
        <div class="nx-aurora__glow nx-aurora__glow--1"></div>
        <div class="nx-aurora__glow nx-aurora__glow--2"></div>
        <div class="relative">
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-100">Available to be paid out</p>
            <p class="mt-2 font-display text-4xl font-bold tracking-tight">${{ number_format($balance, 2) }}</p>
            <div class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm text-teal-100/90">
                <span>Lifetime earned <span class="font-semibold text-white">${{ number_format($lifetime, 2) }}</span></span>
                @if ($nextDate)
                    <span>Next payout <span class="font-semibold text-white">{{ $nextDate->format('M j, Y') }}</span></span>
                @endif
            </div>
        </div>
    </div>

    <p class="mb-5 rounded-xl bg-primary/5 px-4 py-3 text-xs text-slate-500 dark:bg-primary/10 dark:text-slate-400">
        <x-icon name="info" class="mr-1 inline h-3.5 w-3.5" /> Payouts land in your verified payout account automatically each period. Add or verify an account under Wallet → Withdraw.
    </p>

    {{-- Earnings history --}}
    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Earnings history</h2>
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 nx-glass-tile dark:border-white/10">
        @forelse ($ledger as $row)
            <div wire:key="le-{{ $row->id }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0 dark:border-white/5">
                <div class="min-w-0">
                    <p class="font-medium text-slate-800 dark:text-slate-100">
                        @if ($row->type === 'accrual') Profit share
                        @elseif ($row->type === 'hold') Paid out
                        @else Payout returned @endif
                    </p>
                    <p class="text-xs text-slate-400">
                        @if ($row->period_start){{ $row->period_start->format('M j') }} – {{ $row->period_end?->format('M j, Y') }} @else {{ $row->created_at->format('M j, Y') }} @endif
                    </p>
                </div>
                <span class="shrink-0 font-semibold {{ (float) $row->amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">
                    {{ (float) $row->amount >= 0 ? '+' : '−' }}${{ number_format(abs((float) $row->amount), 2) }}
                </span>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400">No earnings yet — your first payout period is still building.</p>
        @endforelse
    </div>

    {{-- Payout status --}}
    @if ($payouts->isNotEmpty())
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Payouts</h2>
        <div class="overflow-hidden rounded-2xl border border-slate-200 nx-glass-tile dark:border-white/10">
            @foreach ($payouts as $po)
                <div wire:key="po-{{ $po->id }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0 dark:border-white/5">
                    <div>
                        <p class="font-medium text-slate-800 dark:text-slate-100">${{ number_format((float) ($po->credit_amount ?: $po->amount), 2) }}</p>
                        <p class="text-xs text-slate-400">{{ $po->created_at->format('M j, Y') }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ['paid' => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300', 'processing' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300', 'approved' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300', 'pending' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300', 'failed' => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300', 'reversed' => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300'][$po->status] ?? 'bg-slate-100 text-slate-600' }}">
                        {{ $po->status === 'pending' ? 'Scheduled' : ucfirst($po->status) }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
