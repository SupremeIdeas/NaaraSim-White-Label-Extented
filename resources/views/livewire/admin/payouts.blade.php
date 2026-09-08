<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Payouts</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Send earned money out to bank accounts. Off by default. In manual mode you
        approve each request before it's sent; declining returns the held funds.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Settings --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <label class="flex items-center justify-between gap-4">
            <span>
                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">Enable payouts</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">When off, no withdrawals can be requested or sent.</span>
            </span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
        </label>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Settlement mode</label>
                <select wire:model="mode"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="manual">Manual — approve each request</option>
                    <option value="autopilot">Autopilot — auto-settle eligible</option>
                </select>
                @error('mode') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Minimum withdrawal (USD)</label>
                <input type="number" step="0.5" min="0" wire:model="minWithdrawal"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('minWithdrawal') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="mt-5 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
            <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
            Save settings
        </button>
    </div>

    {{-- Pending approvals (manual mode) --}}
    <div class="mt-6 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pending approval</h2>
        <div class="text-xs text-slate-500 dark:text-slate-400">${{ number_format($pendingTotal, 2) }} awaiting</div>
    </div>

    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Payee</th>
                    <th class="px-4 py-3">Destination</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $req)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="pending-{{ $req->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $req->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ $req->account?->account_name ?? '—' }}
                            <span class="text-slate-400">· {{ $req->account?->bank_name }} {{ $req->account?->masked_number }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ str_replace('_', ' ', $req->source_bucket) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) $req->amount, 2) }} {{ $req->currency }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $req->id }})" wire:loading.attr="disabled"
                                        wire:confirm="Approve and send this payout?"
                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Approve</button>
                                <button type="button" wire:click="reject({{ $req->id }})" wire:loading.attr="disabled"
                                        wire:confirm="Decline this payout and return the funds?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300">Decline</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">Nothing awaiting approval.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent activity --}}
    <h2 class="mt-6 text-sm font-semibold text-slate-900 dark:text-slate-100">Recent payouts</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Payee</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $req)
                    @php($badge = match ($req->status) {
                        'paid' => 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300',
                        'processing' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
                        default => 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
                    })
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="recent-{{ $req->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $req->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) $req->amount, 2) }} {{ $req->currency }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize {{ $badge }}">{{ $req->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $req->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No payouts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
