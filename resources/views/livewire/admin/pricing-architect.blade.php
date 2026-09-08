<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
                <x-icon name="zap" class="h-6 w-6 text-primary dark:text-teal-300" /> Plan Price with Claude
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Claude analyses your live provider costs and proposes the most profitable, competitive retail prices.
                You approve — and every price stays above its profit floor, guaranteed.
            </p>
        </div>
        @if ($enabled)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-950/50 dark:text-green-300">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Claude active
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Claude off
            </span>
        @endif
    </div>

    @if ($notice)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary dark:text-teal-300">
            <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $notice }}</span>
        </div>
    @endif
    @if ($error)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
        </div>
    @endif

    {{-- Always-on margin monitor (no API call). --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
            <x-icon name="signal" class="h-5 w-5 text-primary" /> Live margin monitor
        </h2>
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-green-50 p-3 text-center dark:bg-green-950/30">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $monitor['healthy'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Healthy</div>
            </div>
            <div class="rounded-xl bg-amber-50 p-3 text-center dark:bg-amber-950/30">
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $monitor['thin'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Thin margin</div>
            </div>
            <div class="rounded-xl bg-red-50 p-3 text-center dark:bg-red-950/30">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $monitor['at_floor'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">At the floor</div>
            </div>
        </div>
        @if (! empty($monitor['flags']))
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                {{ count($monitor['flags']) }} plan(s) need attention — let Claude propose a fix below.
            </p>
        @endif
    </div>

    {{-- Market competitiveness (always-on, admin-tunable model — owner request). --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
            <x-icon name="globe" class="h-5 w-5 text-primary" /> Market competitiveness
        </h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ $market['verdict'] }}</p>
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-green-50 p-3 text-center dark:bg-green-950/30">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $market['competitive'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Competitive</div>
            </div>
            <div class="rounded-xl bg-primary/5 p-3 text-center dark:bg-primary/10">
                <div class="text-2xl font-bold text-primary dark:text-teal-300">{{ $market['keen'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Keen (below market)</div>
            </div>
            <div class="rounded-xl bg-amber-50 p-3 text-center dark:bg-amber-950/30">
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $market['premium'] }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Above market</div>
            </div>
        </div>
        @if (! empty($market['rows']))
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr><th class="py-2">Plan</th><th class="py-2 text-right">Our price</th><th class="py-2 text-right">Est. market</th><th class="py-2 text-right">vs market</th><th class="py-2 text-right">Position</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#243352]">
                        @foreach (collect($market['rows'])->take(8) as $r)
                            <tr wire:key="mkt-{{ $r['plan_id'] }}">
                                <td class="py-2 text-slate-700 dark:text-slate-200">{{ $r['name'] }}</td>
                                <td class="py-2 text-right font-semibold text-slate-900 dark:text-slate-100">${{ number_format($r['current_retail_usd'], 2) }}</td>
                                <td class="py-2 text-right text-slate-500 dark:text-slate-400">${{ number_format($r['market_low'], 2) }}–${{ number_format($r['market_high'], 2) }}</td>
                                <td class="py-2 text-right {{ $r['delta_pct'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-primary dark:text-teal-300' }}">{{ $r['delta_pct'] > 0 ? '+' : '' }}{{ $r['delta_pct'] }}%</td>
                                <td class="py-2 text-right">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                        'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' => $r['position'] === 'competitive',
                                        'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-teal-300' => $r['position'] === 'keen',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $r['position'] === 'premium',
                                    ])>{{ $r['position'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Market estimate is an illustrative, admin-tunable model (per-GB + per-day + base) — not scraped competitor data. When your Anthropic key is on, Claude enriches this with live research in its proposal below.</p>
    </div>

    {{-- Generate / analyse --}}
    @unless ($proposal)
        <div class="mb-6 rounded-2xl border border-dashed border-primary/40 bg-primary/5 p-6 text-center dark:border-primary/40 dark:bg-primary/10">
            @if ($enabled)
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">
                    Ask Claude to analyse every active plan against provider costs and competitor pricing, then
                    propose the best retail prices for your approval.
                </p>
                <button type="button" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="analyze" class="inline-flex items-center gap-2">
                        <x-icon name="zap" class="h-4 w-4" /> Analyse &amp; propose prices
                    </span>
                    <span wire:loading wire:target="analyze" class="inline-flex items-center gap-2">
                        <x-ui.spinner class="h-4 w-4" /> Asking Claude…
                    </span>
                </button>
                @if ($analyzing)
                    <p wire:poll.4s class="mt-3 text-xs text-slate-500 dark:text-slate-400">Analysing… this can take a few seconds.</p>
                @endif
            @else
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">
                    This feature runs on your Anthropic API key. Add it once and Claude will price everything for you.
                </p>
                <a href="{{ route('admin.api-keys') }}" class="inline-flex items-center gap-2 rounded-lg border border-primary/40 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/10 dark:text-teal-300">
                    <x-icon name="key" class="h-4 w-4" /> Add Anthropic key
                </a>
            @endif
        </div>
    @else
        {{-- Pending proposal review --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                    <x-icon name="zap" class="h-5 w-5 text-primary" /> Claude’s proposal
                </h2>
                <span class="text-xs text-slate-400">{{ $proposal->model_used }}</span>
            </div>

            @if ($proposal->summary)
                <p class="mb-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-[#243352] dark:text-slate-300">{{ $proposal->summary }}</p>
            @endif

            @php($meta = $proposal->meta ?? [])
            @if (! empty($meta['recommended_max_discount_pct']) || ! empty($meta['recommended_credit_redeem_pct']))
                <div class="mb-4 flex flex-wrap gap-2 text-xs">
                    @if (! empty($meta['recommended_max_discount_pct']))
                        <span class="rounded-full bg-primary/10 px-3 py-1 font-medium text-primary dark:text-teal-300">Safe max coupon: {{ $meta['recommended_max_discount_pct'] }}%</span>
                    @endif
                    @if (! empty($meta['recommended_credit_redeem_pct']))
                        <span class="rounded-full bg-accent/15 px-3 py-1 font-medium text-amber-700 dark:text-amber-300">Max NaaraCredit redeem: {{ $meta['recommended_credit_redeem_pct'] }}%</span>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                            <th class="py-2 pr-2">Apply</th>
                            <th class="py-2 pr-2">Plan</th>
                            <th class="py-2 pr-2 text-right">Cost</th>
                            <th class="py-2 pr-2 text-right">Now</th>
                            <th class="py-2 pr-2 text-right">Proposed</th>
                            <th class="py-2 pr-2 text-right">Profit</th>
                            <th class="py-2 pr-2 text-right">Floor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proposal->lines as $line)
                            <tr class="border-b border-slate-100 dark:border-[#243352] {{ $line->accepted ? '' : 'opacity-40' }}">
                                <td class="py-2 pr-2">
                                    <input type="checkbox" @checked($line->accepted) wire:click="toggleLine({{ $line->id }})"
                                           class="rounded text-primary focus:ring-primary/40">
                                </td>
                                <td class="py-2 pr-2">
                                    <div class="font-medium text-slate-800 dark:text-slate-100">{{ $line->name }}</div>
                                    @if ($line->rationale)
                                        <div class="text-xs text-slate-400">{{ $line->rationale }}</div>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs text-slate-500 dark:text-slate-400">${{ number_format($line->cost_usd, 2) }}</td>
                                <td class="py-2 pr-2 text-right font-mono text-slate-500 dark:text-slate-400">${{ number_format($line->current_retail_usd, 2) }}</td>
                                <td class="py-2 pr-2 text-right font-mono font-bold text-primary dark:text-teal-300">
                                    ${{ number_format($line->proposed_retail_usd, 2) }}
                                    @if ($line->guard_applied)
                                        <span title="Raised to the profit floor by MarginGuard" class="ml-1 inline-flex align-middle text-amber-500"><x-icon name="shield-check" class="h-3.5 w-3.5" /></span>
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs {{ $line->projected_profit_usd > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600' }}">
                                    +${{ number_format($line->projected_profit_usd, 2) }}
                                    <span class="text-slate-400">({{ number_format($line->projected_margin_pct, 0) }}%)</span>
                                </td>
                                <td class="py-2 pr-2 text-right font-mono text-xs text-slate-400">${{ number_format($line->floor_usd, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-400">
                <x-icon name="shield-check" class="h-4 w-4 text-primary" />
                Every proposed price is clamped to at least cost + your minimum profit — approving can never sell below the floor.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <button type="button" wire:click="approve" wire:loading.attr="disabled" wire:target="approve"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="approve" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Approve &amp; update prices</span>
                    <span wire:loading wire:target="approve" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Applying…</span>
                </button>
                <button type="button" wire:click="reject"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    <x-icon name="x" class="h-4 w-4" /> Dismiss
                </button>
            </div>
        </div>
    @endunless
</div>
