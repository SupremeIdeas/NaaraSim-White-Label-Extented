<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Developer API</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Turn the reselling API on or off and set the developer markups. The floor
        (cost + minimum profit) always applies — you can never sell below cost.
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
                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">Enable the Developer API</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">When off, /api/v1 and the developer portal return 404.</span>
            </span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
        </label>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">eSIM developer markup %</label>
                <input type="number" step="0.1" min="0" wire:model="esimMarkup"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('esimMarkup') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-slate-400">Over wholesale cost. Keep below your retail markup so it stays a discount.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Number / SMS developer markup %</label>
                <input type="number" step="0.1" min="0" wire:model="smsMarkup"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('smsMarkup') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-slate-400">MarginGuard still floors the price at cost + minimum profit.</p>
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="mt-5 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
            <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
            Save settings
        </button>
    </div>

    {{-- Clients overview --}}
    <div class="mt-6 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">API clients</h2>
        <div class="text-xs text-slate-500 dark:text-slate-400">
            {{ $liveCount }} active · ${{ number_format($totalBalance, 2) }} total prepaid
        </div>
    </div>

    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Client</th>
                    <th class="px-4 py-3">Owner</th>
                    <th class="px-4 py-3">Scopes</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last used</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="admin-client-{{ $client->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $client->name }} <span class="text-slate-400">…{{ $client->token_last_four ?? '----' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $client->owner?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ implode(', ', $client->scopes ?? []) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">${{ number_format((float) $client->prepaid_balance_usd, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($client->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:bg-green-950/50 dark:text-green-300">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Revoked</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $client->last_used_at?->diffForHumans() ?? 'never' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No API clients yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
