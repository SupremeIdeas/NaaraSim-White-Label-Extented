{{-- Shared payout dashboard (NAARA-BUILD-22 §4) — one component for partner /
     merchant / referral earners. Status-focused: payouts run automatically, so
     this shows the balance, the free-payout/KYC state, and the ledger history.
     Cost is never shown; only the earner's own payable amounts. --}}
@php
    $label = ['partner' => 'Partner', 'merchant' => 'Merchant', 'referral' => 'Referral'][$earnerType] ?? 'Earnings';
@endphp
<div class="space-y-5">
    {{-- Balance + auto-payout status --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-slate-900/60">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }} earnings balance</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">${{ number_format($balance, 2) }}</p>
                @if ($enabled)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <x-icon name="refresh" class="mr-1 inline h-3.5 w-3.5" />Payouts run automatically to your verified account.
                    </p>
                @else
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Payouts are currently paused by the admin.</p>
                @endif
            </div>

            @if (! $hasVerifiedAccount)
                <a href="{{ route('rewards.withdraw') }}" wire:navigate class="nx-btn nx-btn--ghost !py-2 !text-xs">
                    <x-icon name="wallet" class="h-4 w-4" /> Add a payout account
                </a>
            @endif
        </div>

        {{-- Free-payout / KYC threshold state (§3) — positive-framed. --}}
        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-white/5">
            @if ($exempt)
                <p class="text-xs text-slate-500 dark:text-slate-400"><x-icon name="badge-check" class="mr-1 inline h-3.5 w-3.5 text-emerald-500" />Staff earnings have no payout limits.</p>
            @elseif ($requiresKyc && ! $canWithdraw)
                <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <x-icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <div class="text-sm">
                        <p class="font-semibold text-amber-800 dark:text-amber-300">You've used your {{ $freeCount }} free payouts</p>
                        <p class="text-amber-700 dark:text-amber-400/90">Verify your identity to keep earning and withdrawing — it only takes a minute.</p>
                        <a href="{{ route('account.verify') }}" wire:navigate class="mt-2 inline-flex nx-btn nx-btn--gold !py-1.5 !text-xs">Verify identity</a>
                    </div>
                </div>
            @elseif ($requiresKyc && $canWithdraw)
                <p class="text-xs text-emerald-600 dark:text-emerald-400"><x-icon name="badge-check" class="mr-1 inline h-3.5 w-3.5" />Identity verified — no payout limits.</p>
            @else
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    <x-icon name="check" class="mr-1 inline h-3.5 w-3.5 text-emerald-500" />{{ $remainingFree }} of {{ $freeCount }} free payouts left before identity verification is needed.
                </p>
            @endif
        </div>
    </div>

    {{-- Earnings history --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Earnings history</h3>
        @forelse ($history as $row)
            <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                <div>
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($row->type) }}</span>
                    <span class="text-slate-400">{{ $row->description ?? '' }}</span>
                </div>
                <div class="text-right">
                    <span class="tabular-nums font-semibold {{ (float) $row->amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">
                        {{ (float) $row->amount >= 0 ? '+' : '' }}${{ number_format((float) $row->amount, 2) }}
                    </span>
                    <span class="block text-[11px] text-slate-400">{{ $row->created_at->format('M j, Y') }}</span>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">No earnings yet. As you earn, entries appear here and are paid out automatically.</p>
        @endforelse
    </div>

    {{-- Recent payouts --}}
    @if ($payouts->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Recent payouts</h3>
            @foreach ($payouts as $p)
                @php $tone = match($p->status){
                    'paid' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                    'failed','reversed' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                    'processing','approved' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                    default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                }; @endphp
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="tabular-nums text-slate-700 dark:text-slate-200">${{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] text-slate-400">{{ $p->created_at->format('M j, Y') }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tone }}">{{ ucfirst($p->status) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
