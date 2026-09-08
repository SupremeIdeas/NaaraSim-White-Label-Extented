<div>
    <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Overview</h1>

    {{-- Production misconfiguration banner (BUILD-1 §2.2 / §3.8): loud, admin-
         visible warnings for a sync queue or debug-on in production. --}}
    @foreach (\App\Support\EnvironmentGuard::warnings() as $warn)
        <div wire:key="envwarn-{{ $warn['key'] }}" class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
            <x-icon name="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">{{ $warn['title'] }}</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-700 dark:text-amber-200/80">{{ $warn['detail'] }}</p>
            </div>
        </div>
    @endforeach

    {{-- Payment sandbox indicator (BUILD-2 §8): unmissable if any gateway is
         still pointed at its test environment (real money will NOT move). --}}
    @if (count($sandboxGateways) > 0)
        <div class="mb-4 flex items-start gap-3 rounded-2xl border border-orange-300 bg-orange-50 p-4 dark:border-orange-500/40 dark:bg-orange-500/10">
            <x-icon name="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-orange-600 dark:text-orange-400" />
            <div>
                <p class="text-sm font-bold text-orange-800 dark:text-orange-300">Payment gateway in TEST / sandbox mode</p>
                <p class="mt-1 text-xs leading-relaxed text-orange-700 dark:text-orange-200/80">
                    {{ implode(', ', $sandboxGateways) }} {{ count($sandboxGateways) === 1 ? 'is' : 'are' }} using test keys. Real money will not move until you switch to live keys.
                </p>
            </div>
        </div>
    @endif

    @unless ($privileged)
        {{-- Staff view: scopes only, no business figures. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <x-icon name="shield-check" class="h-4 w-4 text-primary" /> Your access
            </h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">You can work within these scopes. Ask a super admin if you need more.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($myScopes as $scope)
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary-dark dark:bg-primary/20 dark:text-primary">
                        <x-icon name="check" class="h-3 w-3" /> {{ $scopeLabels[$scope] ?? $scope }}
                    </span>
                @empty
                    <span class="text-sm text-slate-400 dark:text-slate-500">No scopes assigned yet.</span>
                @endforelse
            </div>
        </div>
    @else

    {{-- Revenue hero (Module 32 pick — anand_4957 animated-border income card,
         made functional): real 30-day revenue, trend vs the previous window,
         and real last-7-days revenue bars. --}}
    <div class="mb-4 grid gap-4 lg:grid-cols-3">
        <div class="nx-anim-card lg:col-span-2">
            <div class="nx-anim-card__inner">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100/80">
                            <x-icon name="credit-card" class="h-4 w-4" /> Revenue — last 30 days
                        </p>
                        <p class="mt-2 font-display text-4xl font-bold tracking-tight text-white">${{ number_format($revenue, 2) }}</p>
                        <p class="mt-2 text-xs text-teal-100/70">eSIM data + numbers + verification + Naara Gift, before provider cost.</p>
                    </div>
                    @if ($revenueDelta !== null)
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold',
                            'bg-green-400/15 text-green-300' => $revenueDelta >= 0,
                            'bg-red-400/15 text-red-300' => $revenueDelta < 0,
                        ])>
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 {{ $revenueDelta >= 0 ? '-rotate-90' : 'rotate-90' }}" />
                            {{ $revenueDelta >= 0 ? '+' : '' }}{{ number_format($revenueDelta, 1) }}% vs previous 30 days
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-teal-100/80">First 30-day window</span>
                    @endif
                </div>

                <div class="mt-6 flex h-24 items-end gap-2" role="img"
                     aria-label="Daily revenue, last 7 days: {{ collect($revenueBars)->map(fn ($b) => $b['label'].' $'.number_format($b['value'], 2))->implode(', ') }}">
                    @foreach ($revenueBars as $k => $bar)
                        <div wire:key="revbar-{{ $k }}" class="group flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                            <span class="text-[10px] font-semibold text-accent opacity-0 transition group-hover:opacity-100">${{ number_format($bar['value'], 0) }}</span>
                            <div class="nx-anim-card__bar w-full" style="--bar-h: {{ max(4, round($bar['value'] / $barPeak * 100)) }}%; --bar-delay: {{ $k * 90 }}ms"></div>
                            <span class="text-[10px] font-medium text-teal-100/60">{{ $bar['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Revenue split donut (Module 32 pick — code-town3 stat card, made
             functional): 30-day revenue share per product line. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <x-icon name="signal" class="h-4 w-4 text-primary" /> Revenue split (30d)
            </h2>
            <div class="mt-4 flex items-center gap-5">
                <div class="nx-donut shrink-0" @if ($splitTotal > 0) style="--donut: {{ $splitGradient }}" @endif
                     role="img" aria-label="Revenue split by product">
                    <div class="nx-donut__hole">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Total</span>
                        <span class="font-display text-sm font-bold text-slate-900 dark:text-white">${{ number_format($splitTotal, 0) }}</span>
                    </div>
                </div>
                <ul class="min-w-0 flex-1 space-y-2.5 text-sm">
                    @foreach ($split as $k => $seg)
                        <li wire:key="split-{{ $k }}" class="flex items-center justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-2 text-slate-600 dark:text-slate-300">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $seg['color'] }}"></span>
                                <span class="truncate">{{ $seg['label'] }}</span>
                            </span>
                            <span class="shrink-0 font-semibold text-slate-900 dark:text-slate-100">
                                {{ $splitTotal > 0 ? number_format($seg['value'] / $splitTotal * 100, 0).'%' : '—' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($splitTotal <= 0)
                <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">No sales in the last 30 days yet — the split fills in with your first orders.</p>
            @endif
        </div>
    </div>

    {{-- 30-day cost/profit tiles (admin-only figures) --}}
    <div class="mb-8 grid grid-cols-3 gap-4">
        @php
            $tiles = [
                ['Cost (30d)', '$'.number_format($cost, 2), 'package', 'text-slate-900 dark:text-slate-100'],
                ['Gross profit', '$'.number_format($profit, 2), 'zap', 'text-green-600 dark:text-green-400'],
                ['Gross margin', number_format($margin, 1).'%', 'signal', 'text-green-600 dark:text-green-400'],
            ];
        @endphp
        @foreach ($tiles as [$label, $value, $icon, $valueClass])
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                </div>
                <div class="mt-2 text-2xl font-bold {{ $valueClass }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>
    <p class="-mt-6 mb-8 text-xs text-slate-400 dark:text-slate-500">
        Cost / profit / margin cover eSIM + numbers + verification only — Naara Gift's provider cost isn't tracked per order (see revenue split above for its revenue contribution).
    </p>

    {{-- Oversight metrics (owner request): users, weekly/monthly profit, API. --}}
    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $oversight = [
                ['Registered users', number_format($totalUsers), '+'.number_format($newUsersWeek).' this week', 'id-card', 'text-slate-900 dark:text-slate-100'],
                ['Profit — this week', '$'.number_format($profitWeek, 2), 'revenue − provider cost', 'zap', 'text-green-600 dark:text-green-400'],
                ['Profit — this month', '$'.number_format($profitMonth, 2), 'month to date', 'wallet', 'text-green-600 dark:text-green-400'],
                ['API orders — week', number_format($apiOrdersWeek), '$'.number_format($apiRevenueWeek, 2).' billed', 'key', 'text-slate-900 dark:text-slate-100'],
            ];
        @endphp
        @foreach ($oversight as [$label, $value, $sub, $icon, $valueClass])
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                </div>
                <div class="mt-1.5 text-2xl font-bold {{ $valueClass }}">{{ $value }}</div>
                <div class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    {{-- Most-bought by country + most-used models (owner request). --}}
    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <x-icon name="globe" class="h-4 w-4" /> Most-bought numbers by country <span class="text-xs font-normal text-slate-400">· 30d</span>
            </h3>
            @forelse ($topCountries as $row)
                @php($__max = max(array_column($topCountries, 'count')) ?: 1)
                <div class="mb-2 flex items-center gap-3" wire:key="tc-{{ $row['country'] }}">
                    <span class="w-28 shrink-0 truncate text-sm capitalize text-slate-700 dark:text-slate-200">{{ str_replace('_', ' ', $row['country']) }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-[#243352]">
                        <div class="h-full rounded-full bg-primary" style="width: {{ round($row['count'] / $__max * 100) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $row['count'] }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400 dark:text-slate-500">No number orders yet.</p>
            @endforelse
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <x-icon name="grid" class="h-4 w-4" /> Most-used NaaraSim models <span class="text-xs font-normal text-slate-400">· 30d</span>
            </h3>
            @forelse ($topModels as $row)
                @php($__mmax = max(array_column($topModels, 'count')) ?: 1)
                <div class="mb-2 flex items-center gap-3" wire:key="tm-{{ $row['label'] }}">
                    <span class="w-28 shrink-0 truncate text-sm text-slate-700 dark:text-slate-200">{{ $row['label'] }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-[#243352]">
                        <div class="h-full rounded-full bg-accent" style="width: {{ round($row['count'] / $__mmax * 100) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $row['count'] }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400 dark:text-slate-500">No orders yet.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Provider health — every eSIM + number provider (BUILD-5 §2), not just
             the wallet-funded ones. `down` = API unreachable/erroring. --}}
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="signal" class="h-4 w-4" /> Provider health
            </h2>
            <div class="space-y-2">
                @forelse ($health as $provider => $info)
                    <div wire:key="health-{{ $provider }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <span class="flex items-center gap-2">
                            <span class="font-medium capitalize text-slate-900 dark:text-slate-100">{{ $provider }}</span>
                            @if (($info['stack'] ?? '') !== '')
                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-[#243352] dark:text-slate-400">{{ $info['stack'] }}</span>
                            @endif
                        </span>
                        <div class="flex items-center gap-3">
                            @if (! is_null($info['balance'] ?? null))
                                <span class="text-sm text-slate-500 dark:text-slate-400">${{ number_format((float) $info['balance'], 2) }}</span>
                            @endif
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => ($info['status'] ?? '') === 'ok',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => ($info['status'] ?? '') === 'low',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => in_array($info['status'] ?? '', ['error', 'down'], true),
                                'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300' => ($info['status'] ?? '') === 'configured',
                                'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ($info['status'] ?? '') === 'coming_soon',
                            ])>{{ ucfirst(str_replace('_', ' ', $info['status'] ?? 'unknown')) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                        No health data yet. Run <code class="font-mono">providers:health-check</code>.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Active / Coming Soon per product --}}
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="badge-check" class="h-4 w-4" /> Products
            </h2>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($statuses as $provider => $label)
                    <div wire:key="status-{{ $provider }}" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <span class="capitalize text-slate-700 dark:text-slate-200">{{ $provider }}</span>
                        <x-ui.tag :variant="$label === 'Active' ? 'live' : 'soon'">{{ $label }}</x-ui.tag>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    @endunless
</div>
