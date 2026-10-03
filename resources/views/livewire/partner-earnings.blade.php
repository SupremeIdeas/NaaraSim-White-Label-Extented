{{-- Partner earnings on the skin system (S3 Batch 6). The share % is never shown, only dollars. --}}
<div>
<x-nx.page class="ns-pg">
    <h1 class="ns-h1" style="margin-top:6px">Partner earnings</h1>
    <p class="ns-sub">Your share of the platform, paid to you every {{ $partner->payout_cadence === 'weekly' ? 'week' : 'month' }}.</p>

    @if ($suspended)
        <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:16px"><x-nx.icon name="info" /><div><b>Account paused</b><p>Your partner account is paused. Please contact support.</p></div></div>
    @endif

    {{-- Balance card (dollars only: the share % is never shown). --}}
    <x-nx.balance-hero style="margin-top:16px" label="Available to be paid out" :amount="'$'.number_format($balance, 2)" :sub="'Lifetime earned $'.number_format($lifetime, 2).($nextDate ? ' · Next payout '.$nextDate->format('M j, Y') : '')" />

    <x-nx.note style="margin-top:14px" icon="info">Payouts land in your verified payout account automatically each period. Add or verify an account under Wallet → Withdraw.</x-nx.note>

    {{-- Earnings history --}}
    <span class="ns-lbl" style="margin-top:22px">Earnings history</span>
    <div class="ns-pg__ledger ns-ring">
        @forelse ($ledger as $row)
            <div wire:key="le-{{ $row->id }}" class="ns-pg__lrow">
                <div style="min-width:0">
                    <b>@if ($row->type === 'accrual') Profit share @elseif ($row->type === 'hold') Paid out @else Payout returned @endif</b>
                    <small>@if ($row->period_start){{ $row->period_start->format('M j') }} – {{ $row->period_end?->format('M j, Y') }} @else {{ $row->created_at->format('M j, Y') }} @endif</small>
                </div>
                <span class="{{ (float) $row->amount >= 0 ? 'is-pos' : 'is-neg' }}" style="flex:none">{{ (float) $row->amount >= 0 ? '+' : '−' }}${{ number_format(abs((float) $row->amount), 2) }}</span>
            </div>
        @empty
            <p class="ns-sub" style="padding:28px 14px;text-align:center;margin:0">No earnings yet. Your first payout period is still building.</p>
        @endforelse
    </div>

    {{-- Payout status --}}
    @if ($payouts->isNotEmpty())
        <span class="ns-lbl" style="margin-top:22px">Payouts</span>
        <div class="ns-pg__ledger ns-ring">
            @foreach ($payouts as $po)
                @php($tone = ['paid' => 'ns-st--ok', 'processing' => 'ns-st--info', 'approved' => 'ns-st--info', 'failed' => 'ns-st--bad', 'reversed' => 'ns-st--bad'][$po->status] ?? '')
                <div wire:key="po-{{ $po->id }}" class="ns-pg__lrow">
                    <div><b>${{ number_format((float) ($po->credit_amount ?: $po->amount), 2) }}</b><small>{{ $po->created_at->format('M j, Y') }}</small></div>
                    <span class="ns-st {{ $tone }}">{{ $po->status === 'pending' ? 'Scheduled' : ucfirst($po->status) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-nx.page>
</div>
