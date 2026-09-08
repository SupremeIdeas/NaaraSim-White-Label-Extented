@if ($order)
    {{-- Success — number reserved, code polling in the background. --}}
    <div class="py-4 text-center">
        <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 text-green-600 dark:bg-green-950/50 dark:text-green-300">
            <x-icon name="badge-check" class="h-7 w-7" />
        </span>
        <p class="text-lg font-bold text-slate-900 dark:text-white">Number reserved</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your verification number is ready — we’re fetching the code now.</p>

        <div class="mx-auto mt-4 max-w-xs rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
            <p class="text-[11px] uppercase tracking-wide text-slate-400">Your number</p>
            <p class="mt-0.5 select-all font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $order->phone_number }}</p>
            @if ($order->otp_code)
                <p class="mt-3 text-[11px] uppercase tracking-wide text-slate-400">Code</p>
                <p class="select-all font-mono text-2xl font-bold text-primary dark:text-teal-300">{{ $order->otp_code }}</p>
            @else
                <p class="mt-3 inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <x-ui.spinner class="h-4 w-4" /> Waiting for the code…
                </p>
            @endif
        </div>

        <div class="mt-5 flex gap-2">
            <button type="button" wire:click="reset_" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Buy another</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex-1 rounded-xl bg-primary py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-dark">View on dashboard</a>
        </div>
    </div>
@else
    {{-- Manual / Smart Buy switcher --}}
    <div class="mb-4 inline-flex w-full rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
        @foreach (['manual' => 'Manual Buy', 'smart' => 'Smart Buy'] as $k => $label)
            <button type="button" wire:click="$set('buyMode', '{{ $k }}')"
                    class="flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $buyMode === $k ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($buyMode === 'smart')
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Pick a service — we choose the best-priced country and network for you automatically.</p>
    @endif

    <div class="space-y-3">
        {{-- Service step --}}
        <button type="button" wire:click="pickService"
                class="flex w-full items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-primary/40 dark:border-white/10 dark:bg-white/5">
            <span class="flex min-w-0 items-center gap-3">
                <x-service-icon :slug="$service" class="h-9 w-9 shrink-0" />
                <span class="min-w-0">
                    <span class="block text-[11px] uppercase tracking-wide text-slate-400">Service</span>
                    <span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $serviceName }}</span>
                </span>
            </span>
            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
        </button>

        {{-- Country step (hidden in Smart Buy — auto-picked) --}}
        @if ($buyMode === 'manual')
            <button type="button" wire:click="pickCountry"
                    class="flex w-full items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-primary/40 dark:border-white/10 dark:bg-white/5">
                <span class="flex min-w-0 items-center gap-3">
                    <x-country-flag :country="$country" class="h-6 w-8 shrink-0" />
                    <span class="min-w-0">
                        <span class="block text-[11px] uppercase tracking-wide text-slate-400">Country</span>
                        <span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $countryName }}</span>
                    </span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
            </button>
        @endif
    </div>

    {{-- Step 3 — operator comparison (manual only). Networks merged across the
         lane, RETAIL-priced (provider + cost hidden), best flagged. Prices /
         Statistics tabs + CSV export. --}}
    @if ($buyMode === 'manual' && count($operators))
        <div class="mt-4" x-data="{ tab: @entangle('opTab').live }">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Networks</p>
                <div class="flex items-center gap-2">
                    <div class="flex rounded-full border border-slate-200 bg-slate-100 p-0.5 text-[11px] font-semibold dark:border-white/10 dark:bg-white/5">
                        <button type="button" @click="tab = 'prices'" :class="tab === 'prices' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-full px-2.5 py-1 transition">Prices</button>
                        <button type="button" @click="tab = 'stats'" :class="tab === 'stats' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-full px-2.5 py-1 transition">Statistics</button>
                    </div>
                    <button type="button" wire:click="exportOperatorsCsv" title="Export CSV"
                            class="flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-white/10"><x-icon name="download" class="h-4 w-4" /></button>
                </div>
            </div>

            <div class="space-y-1.5">
                {{-- Auto / best --}}
                <button type="button" wire:click="pickOperator('')"
                        class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition {{ $operator === '' ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 dark:border-white/10' }}">
                    <span class="flex items-center gap-2 font-semibold text-slate-800 dark:text-slate-100"><x-icon name="zap" class="h-4 w-4 text-accent-dark dark:text-accent" /> Auto — best network</span>
                    <span class="text-xs text-slate-400">Recommended</span>
                </button>

                @foreach ($operators as $op)
                    <button type="button" @disabled(! $op['in_stock']) wire:click="pickOperator('{{ $op['operator'] }}')"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition disabled:opacity-45
                                   {{ $operator === $op['operator'] && $operator !== '' ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 dark:border-white/10' }}">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $op['label'] }}</span>
                            @if ($op['best'])
                                <span class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase text-green-700 dark:bg-green-950/50 dark:text-green-300">Best</span>
                            @endif
                            @unless ($op['in_stock'])<span class="shrink-0 text-[10px] font-semibold uppercase text-slate-400">Out of stock</span>@endunless
                        </span>
                        {{-- Prices tab --}}
                        <span x-show="tab === 'prices'" class="shrink-0 font-semibold text-slate-900 dark:text-white">${{ number_format($op['retail'], 2) }}</span>
                        {{-- Statistics tab --}}
                        <span x-show="tab === 'stats'" x-cloak class="shrink-0 text-right text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $op['available'] }} avail.@if ($op['success'] !== null) · {{ $op['success'] }}%@endif
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($error)
        <p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</p>
    @endif

    {{-- NaaraCredits redemption (loyalty) — margin-capped server-side. --}}
    @if ($modalPrice !== null && $creditsEnabled && $creditBalance > 0 && $creditQuote['usd'] > 0)
        <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 p-3 dark:border-primary/30 dark:bg-primary/10">
            <input type="checkbox" wire:model.live="useCredits" class="mt-0.5 rounded text-primary focus:ring-primary/40">
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-naara-coin class="h-4 w-4" /> Use my NaaraCredits
                </span>
                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                    You have {{ number_format($creditBalance, 0) }} credits. Apply
                    <span class="font-semibold">{{ number_format($creditQuote['credits'], 0) }}</span>
                    to save <span class="font-semibold text-green-600 dark:text-green-400">${{ number_format($creditQuote['usd'], 2) }}</span> on this order.
                </span>
            </span>
        </label>
    @endif

    {{-- Sticky checkout --}}
    <div class="sticky bottom-0 -mx-5 mt-5 border-t border-slate-100 bg-white px-5 pt-4 dark:border-white/10 dark:bg-[#0D1B2A]">
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm text-slate-500 dark:text-slate-400">You pay</span>
            <span class="text-lg font-bold text-slate-900 dark:text-white">
                @if ($modalPrice !== null)${{ number_format($useCredits ? $modalPrice - $creditQuote['usd'] : $modalPrice, 2) }}@else <span class="text-sm font-medium text-slate-400">Priced at reservation</span>@endif
            </span>
        </div>
        <button type="button" wire:click="order" wire:loading.attr="disabled" wire:target="order"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <span wire:loading.remove wire:target="order"><x-icon name="shield-check" class="mr-1 inline h-4 w-4" /> Get my code</span>
            <span wire:loading wire:target="order" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Reserving…</span>
        </button>
        <p class="mt-2 text-center text-[11px] text-slate-400">Charged from your wallet. Auto-refund if no code arrives.</p>
    </div>
@endif
