<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Analytics</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">The platform deep-dive — wallet/FX, merchants, operational health, provider reliability. The Overview page stays the fast, glanceable summary.</p>
        </div>
        {{-- Period toggle (same convention as Reconciliation) --}}
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

    {{-- ============================== WALLET & FX ============================== --}}
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <x-icon name="wallet" class="h-4 w-4" /> Wallet & FX
        </h2>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Platform USD liability</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">${{ number_format($usdLiability, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Every wallet's usd_balance, right now</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Deposits — {{ $days }}d</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">${{ number_format(collect($topUpByGateway)->sum('volume'), 2) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Across every gateway</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <a href="{{ route('admin.reconciliation') }}" wire:navigate class="group flex h-full flex-col justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Full reconciliation</p>
                    <span class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-primary group-hover:underline dark:text-teal-300">
                        Open report <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                    </span>
                </a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Deposit volume by gateway</h3>
                @forelse ($topUpByGateway as $row)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                        <span class="capitalize text-slate-600 dark:text-slate-300">{{ $row['gateway'] }}</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">${{ number_format($row['volume'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No gateway charges in this period.</p>
                @endforelse
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h3 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Deposit volume by original currency</h3>
                <p class="mb-3 text-[11px] text-slate-400">What users actually typed in — before any FX conversion to USD.</p>
                @forelse ($topUpByCurrency as $row)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                        <span class="text-slate-600 dark:text-slate-300">{{ $row['currency'] }}</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['volume'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No deposits in this period.</p>
                @endforelse
            </div>
        </div>

        <div x-data="{ open: false }" class="mt-4">
            <button type="button" @click="open = ! open" class="flex items-center gap-1.5 text-xs font-medium text-primary hover:underline dark:text-teal-300">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" /> Live FX rates (USD → currency)
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-[#2D4060] dark:bg-[#1A2840] sm:grid-cols-3">
                @foreach ($fxRates as $row)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">{{ $row['currency'] }}</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ number_format($row['usd_rate'], 4) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== MERCHANTS ============================== --}}
    <section class="mb-8">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="id-card" class="h-4 w-4" /> Merchants
            </h2>
            <a href="{{ route('admin.merchants') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline dark:text-teal-300">
                Manage merchants <x-icon name="chevron-right" class="h-3.5 w-3.5" />
            </a>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Merchant earnings — {{ $days }}d</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">${{ number_format($merchantEarnings, 2) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Accrued commission</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Top merchants by client-order volume</h3>
            @forelse ($merchantLeaderboard as $row)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="min-w-0 truncate text-slate-600 dark:text-slate-300">{{ $row['business_name'] }} <span class="text-xs text-slate-400">· {{ $row['clients'] }} {{ Str::plural('client', $row['clients']) }}</span></span>
                    <span class="shrink-0 font-semibold text-slate-900 dark:text-slate-100">${{ number_format($row['volume'], 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No merchant client orders in this period.</p>
            @endforelse
        </div>
    </section>

    {{-- ============================== OPERATIONAL HEALTH ============================== --}}
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <x-icon name="shield-check" class="h-4 w-4" /> Operational health
        </h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">KYC approval rate</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $kyc['rate_pct'] !== null ? number_format($kyc['rate_pct'], 1).'%' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ $kyc['approved'] }} of {{ $kyc['total'] }} decisions</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Refund rate</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $refunds['rate_pct'] !== null ? number_format($refunds['rate_pct'], 1).'%' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">${{ number_format($refunds['refunds'], 2) }} of ${{ number_format($refunds['revenue'], 2) }} revenue</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Support — open</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $support['open'] }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ $support['resolved'] }} resolved this period</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Avg. resolution time</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $support['avg_resolution_hours'] !== null ? number_format($support['avg_resolution_hours'], 1).'h' : '—' }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Created → last update, resolved tickets</p>
            </div>
        </div>
    </section>

    {{-- ============================== PROVIDER RELIABILITY + eSIM USAGE ============================== --}}
    <section>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="signal" class="h-4 w-4" /> Provider reliability & eSIM usage
            </h2>
            @can('nci.view')
                <a href="{{ route('admin.nci.health') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline dark:text-teal-300">
                    Full health monitor <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                </a>
            @endcan
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Providers tracked</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $providerReliability['tracked'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Degraded (circuit open)</p>
                <p @class(['mt-1 text-xl font-bold', 'text-red-600 dark:text-red-400' => $providerReliability['degraded'] > 0, 'text-slate-900 dark:text-slate-100' => $providerReliability['degraded'] === 0])>{{ $providerReliability['degraded'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Avg. success rate (24h)</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $providerReliability['avg_success_rate_pct'] !== null ? number_format($providerReliability['avg_success_rate_pct'], 1).'%' : '—' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Data used this week</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                    @if ($esimUsage['total_mb_this_week'] >= 1024)
                        {{ number_format($esimUsage['total_mb_this_week'] / 1024, 1) }} GB
                    @else
                        {{ number_format($esimUsage['total_mb_this_week'], 0) }} MB
                    @endif
                </p>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ $esimUsage['active_esims'] }} active · {{ $esimUsage['expiring_soon'] }} expiring soon</p>
            </div>
        </div>

        @if (count($providerReliability['worst']))
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Lowest success rate (24h)</h3>
                @foreach ($providerReliability['worst'] as $row)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                        <span class="flex items-center gap-2">
                            <span class="capitalize text-slate-600 dark:text-slate-300">{{ $row['provider_key'] }}</span>
                            @if ($row['circuit_breaker_state'] !== 'closed')
                                <span class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ str_replace('_', ' ', $row['circuit_breaker_state']) }}</span>
                            @endif
                        </span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['success_rate_pct'], 1) }}%</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
