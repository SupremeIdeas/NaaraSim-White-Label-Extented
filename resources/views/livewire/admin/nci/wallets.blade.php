<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Wallets</h1>
    <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">Provider wallet balances, a low-balance flag, and a volume-based burn estimate from recent orders.</p>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="border-b border-slate-100 text-[11px] uppercase tracking-wide text-slate-400 dark:border-[#243352]">
                <tr><th class="px-4 py-2.5 font-medium">Provider</th><th class="px-3 py-2.5 font-medium">Balance</th><th class="px-3 py-2.5 font-medium">Burn (est/day)</th><th class="px-3 py-2.5 font-medium">Flag</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-[#243352]/70">
                @forelse ($rows as $r)
                    <tr wire:key="w-{{ $r['provider_key'] }}" class="hover:bg-slate-50 dark:hover:bg-[#243352]/40">
                        <td class="px-4 py-2.5"><a href="{{ route('admin.nci.provider', $r['provider_key']) }}" wire:navigate class="font-semibold capitalize text-primary hover:underline dark:text-teal-300">{{ $r['provider_key'] }}</a> <span class="ml-1 text-[10px] uppercase text-slate-400">{{ $r['stack'] }}</span></td>
                        <td class="px-3 py-2.5 font-semibold text-slate-800 dark:text-slate-100">${{ number_format((float) $r['balance'], 2) }}</td>
                        <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ $r['burn'] }} orders/day</td>
                        <td class="px-3 py-2.5">@if ($r['low'])<x-nci.pill kind="status" value="low" />@else <span class="text-xs text-slate-400">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-sm text-slate-400">No wallet-funded providers with a balance yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
