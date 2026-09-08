<div class="mx-auto max-w-5xl" x-data="{ refund: false }" @close-refund.window="refund = false">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Refunds &amp; Disputes</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Refund a wallet top-up, and watch card disputes. Refunds reverse the gateway charge and the wallet ledger together, and are fully audited.</p>
    </div>

    {{-- Disputes --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Card disputes</h2>
        @if ($disputes->isEmpty())
            <p class="text-sm text-slate-400 dark:text-slate-500">No disputes. Contested charges appear here and their funds are frozen until resolved.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-400">
                        <tr>
                            <th class="py-2 pr-3">Gateway</th><th class="py-2 pr-3">User</th>
                            <th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Frozen</th>
                            <th class="py-2 pr-3">Status</th><th class="py-2 pr-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($disputes as $d)
                            <tr wire:key="dispute-{{ $d->id }}">
                                <td class="py-2 pr-3 font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($d->gateway) }}</td>
                                <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ $d->user?->email ?? '—' }}</td>
                                <td class="py-2 pr-3">{{ number_format((float) $d->amount, 2) }} {{ $d->currency }}</td>
                                <td class="py-2 pr-3">{{ number_format((float) $d->frozen_amount, 2) }}</td>
                                <td class="py-2 pr-3">
                                    @php($tone = ['open' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'won' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'lost' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'][$d->status] ?? '')
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $tone }}">{{ $d->status }}</span>
                                </td>
                                <td class="py-2 pr-3 text-slate-400">{{ $d->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Top-ups (refundable) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Wallet top-ups</h2>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search email or reference…"
                   class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-400">
                    <tr>
                        <th class="py-2 pr-3">User</th><th class="py-2 pr-3">Gateway</th>
                        <th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">When</th><th class="py-2 pr-3 text-right">Refund</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse ($topups as $t)
                        @php($existing = $refundByTxn[$t->id] ?? null)
                        <tr wire:key="topup-{{ $t->id }}">
                            <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ $t->user?->email ?? '—' }}</td>
                            <td class="py-2 pr-3 font-medium text-slate-700 dark:text-slate-200">{{ ucfirst(explode(':', $t->reference)[1] ?? '') }}</td>
                            <td class="py-2 pr-3">{{ number_format((float) $t->amount, 2) }} {{ $t->currency }}</td>
                            <td class="py-2 pr-3 text-slate-400">{{ $t->created_at?->diffForHumans() }}</td>
                            <td class="py-2 pr-3 text-right">
                                @if ($existing)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ $existing->status }}</span>
                                @else
                                    <button type="button" wire:click="openRefund({{ $t->id }})" @click="refund = true"
                                            class="rounded-lg border border-red-300 px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-400">Refund</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-slate-400">No top-ups yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $topups->links() }}</div>
    </div>

    {{-- Refund confirm sheet --}}
    <div x-show="refund" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="refund = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="refund = false"></div>
        <div x-show="refund" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            @if ($refundTxn)
                <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Refund this top-up?</h2>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ number_format((float) $refundTxn->amount, 2) }} {{ $refundTxn->currency }} to {{ $refundTxn->user?->email }}.
                    This calls the gateway's refund and reverses the wallet. It can't be undone. If the user already spent the funds, it will be refused.
                </p>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reason (audited)</label>
                <input type="text" wire:model="refundReason" placeholder="e.g. duplicate charge"
                       class="mb-4 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                <button type="button" wire:click="refund" wire:loading.attr="disabled" wire:target="refund"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-red-600 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="refund">Refund now</span>
                    <span wire:loading wire:target="refund" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Refunding…</span>
                </button>
            @endif
        </div>
    </div>
</div>
