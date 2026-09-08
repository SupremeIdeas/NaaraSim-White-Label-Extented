<div class="mx-auto max-w-lg">
    <a href="{{ route('catalogue') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to catalogue
    </a>

    <div class="relative rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        {{-- Scoped action loader (audit §7) — pulsing logo over the card while the
             purchase is in flight; dismisses the instant the action resolves. --}}
        <x-brand-loader target="purchase" :overlay="true" label="Processing your order…" />

        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="shield-check" class="h-5 w-5" />
            </span>
            <div>
                <h1 class="text-xl font-bold leading-tight text-slate-900 dark:text-slate-100">Checkout</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Secure wallet payment · instant delivery</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-[#243352]">
            <div class="flex items-center justify-between gap-3">
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $plan->name }}</span>
                <span class="inline-flex shrink-0 items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                    <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $plan->type ?? 'Data' }}
                </span>
            </div>

            {{-- Real synced plan facts (esim_upgrade Part 2): data, validity, and
                 — for Naara Connect — a calls + data badge. --}}
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-[#1B2A44] dark:text-slate-200">
                    <x-icon name="signal" class="h-3.5 w-3.5 text-primary" />
                    {{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB' : 'Unlimited data' }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-[#1B2A44] dark:text-slate-200">
                    <x-icon name="refresh" class="h-3.5 w-3.5 text-primary" />
                    {{ $plan->validity_days ? $plan->validity_days.' days' : 'Flexible validity' }}
                </span>
                @if ($plan->has_voice)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-teal-300">
                        <x-icon name="phone" class="h-3.5 w-3.5" /> Calls + Data
                    </span>
                @endif
            </div>

            {{-- Coverage: flags for the countries this plan reaches. --}}
            @php($__countries = array_values(array_filter((array) $plan->countries)))
            @if (! empty($__countries))
                <div class="mt-3 flex items-center gap-1.5">
                    <span class="text-xs text-slate-400 dark:text-slate-500">Works in</span>
                    <span class="flex flex-wrap items-center gap-1">
                        @foreach (array_slice($__countries, 0, 6) as $__iso)
                            <x-country-flag :country="$__iso" class="h-4 w-6" />
                        @endforeach
                        @if (count($__countries) > 6)
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">+{{ count($__countries) - 6 }} more</span>
                        @elseif (count($__countries) === 1)
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ \App\Support\CountryNames::name($__countries[0]) }}</span>
                        @endif
                    </span>
                </div>
            @endif
            {{-- Localized price (owner request): USD default + the viewer's
                 local-currency equivalent (live FX; charge is always in USD). --}}
            @php($__cur = \App\Support\LocaleCurrency::resolve(auth()->user()))
            @php($__fx = app(\App\Services\Pricing\CurrencyService::class))
            <div class="mt-4 flex items-end justify-between border-t border-slate-200 pt-4 dark:border-[var(--brand-card-border-dark)]">
                <span class="text-sm text-slate-500 dark:text-slate-400">You pay</span>
                <div class="text-right">
                    @if ($couponPrice !== null)
                        <div class="text-xs text-slate-400 line-through dark:text-slate-500">{{ $plan->display_price['usd'] }}</div>
                        <div class="text-2xl font-bold text-primary dark:text-teal-300">${{ number_format($couponPrice, 2) }}</div>
                        @if ($__cur !== 'USD')<div class="text-xs text-slate-400 dark:text-slate-500">≈ {{ $__fx->format($couponPrice, $__cur) }}</div>@endif
                        <div class="text-xs font-medium text-green-600 dark:text-green-400">You save ${{ number_format($couponSaved, 2) }}</div>
                    @else
                        <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $plan->display_price['usd'] }}</div>
                        @if ($__cur !== 'USD')
                            <div class="text-xs text-slate-500 dark:text-slate-400">≈ {{ $__fx->format((float) $plan->final_retail_usd, $__cur) }}</div>
                        @else
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $plan->display_price['ngn'] }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Coupon code (Module 31) — discount is margin-guarded server-side. --}}
        @unless ($done)
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 p-3 dark:border-[var(--brand-card-border-dark)]">
                @if ($couponPrice !== null)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="inline-flex items-center gap-2 font-medium text-green-700 dark:text-green-400">
                            <x-icon name="badge-check" class="h-4 w-4" /> Coupon <span class="font-mono uppercase">{{ $coupon }}</span> applied
                        </span>
                        <button type="button" wire:click="removeCoupon" class="text-xs text-slate-400 underline hover:text-slate-600 dark:hover:text-slate-300">Remove</button>
                    </div>
                @else
                    <div class="flex gap-2">
                        <input wire:model="coupon" wire:keydown.enter="applyCoupon" type="text" placeholder="Have a coupon code?" autocomplete="off"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase tracking-wider dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        <button type="button" wire:click="applyCoupon" wire:loading.attr="disabled" wire:target="applyCoupon"
                                class="shrink-0 rounded-lg border border-primary/40 px-3 py-2 text-sm font-semibold text-primary hover:bg-primary/10 disabled:opacity-60 dark:text-teal-300">
                            <span wire:loading.remove wire:target="applyCoupon">Apply</span>
                            <span wire:loading wire:target="applyCoupon" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Checking…</span>
                        </button>
                    </div>
                    @if ($couponError)
                        <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $couponError }}</p>
                    @endif
                @endif
            </div>

            {{-- NaaraCredits redemption (loyalty) — margin-capped server-side. --}}
            @if ($creditsEnabled && $creditBalance > 0 && $creditQuote['usd'] > 0)
                <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 p-3 dark:border-primary/40 dark:bg-primary/10">
                    <input type="checkbox" wire:model.live="useCredits" class="mt-0.5 rounded text-primary focus:ring-primary/40">
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            <x-naara-coin class="h-4 w-4" /> Use my NaaraCredits
                        </span>
                        <span class="mt-0.5 block text-xs text-slate-600 dark:text-slate-300">
                            You have {{ number_format($creditBalance, 0) }} credits. Apply
                            <span class="font-semibold">{{ number_format($creditQuote['credits'], 0) }}</span>
                            to save <span class="font-semibold text-green-600 dark:text-green-400">${{ number_format($creditQuote['usd'], 2) }}</span> on this order.
                        </span>
                    </span>
                </label>
                @if ($useCredits)
                    <div class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-[#243352]">
                        <span class="text-slate-500 dark:text-slate-400">Charged to wallet after credits</span>
                        <span class="font-bold text-primary dark:text-teal-300">${{ number_format(($couponPrice ?? (float) $plan->final_retail_usd) - $creditQuote['usd'], 2) }}</span>
                    </div>
                @endif
            @endif
        @endunless

        @if ($error)
            <div class="mt-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
            </div>
        @endif

        @if ($done)
            <div class="mt-4 flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $message }}</span>
            </div>
            <a href="{{ route('dashboard') }}" wire:navigate
               class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-primary via-primary-dark to-navy px-4 py-3.5 font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl">
                Go to My Connectivity <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            {{-- Device-compatibility check — runs BEFORE purchase (Section 32) --}}
            <div class="mt-6 rounded-xl border border-slate-200 p-4 dark:border-[var(--brand-card-border-dark)]">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="phone" class="h-4 w-4 text-primary" /> Does your phone support eSIM?
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">An eSIM only works on an eSIM-capable phone. Let’s check yours first.</p>

                <div class="mt-3 flex gap-2">
                    <input wire:model="device" type="text" placeholder="e.g. iPhone 14, Galaxy S23, Pixel 7"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    <button type="button" wire:click="checkDevice" wire:loading.attr="disabled" wire:target="checkDevice"
                            class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">Check</button>
                </div>

                @if ($deviceResult === true)
                    <p class="mt-2 flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400"><x-icon name="badge-check" class="h-4 w-4" /> Great — that device supports eSIM.</p>
                @elseif ($deviceResult === false)
                    <p class="mt-2 flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400"><x-icon name="x" class="h-4 w-4" /> That device does not support eSIM — a purchase won’t work on it.</p>
                @elseif ($deviceResult === null && $device !== '')
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">We’re not sure about that model. {{ \App\Support\Niche\DeviceCompat::howToCheck() }}</p>
                @endif

                @if ($deviceResult !== false)
                    <label class="mt-3 flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model.live="deviceConfirmed" class="mt-0.5 rounded text-primary">
                        <span>I confirm my device supports eSIM (dial <span class="font-mono">*#06#</span> to find your EID).</span>
                    </label>
                @endif
            </div>

            <button type="button" wire:click="purchase" wire:loading.attr="disabled" wire:target="purchase"
                    @disabled(! $deviceConfirmed)
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-primary via-primary-dark to-navy px-4 py-3.5 font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0">
                <span wire:loading.remove wire:target="purchase" class="inline-flex items-center gap-2">
                    <x-icon name="shield-check" class="h-5 w-5" /> Pay with wallet
                </span>
                <span wire:loading wire:target="purchase" class="inline-flex items-center gap-2">
                    <x-ui.spinner class="h-5 w-5" /> Processing…
                </span>
            </button>
            <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">Charged securely from your NaaraSim wallet. <a href="{{ route('refund-policy') }}" class="text-primary hover:underline">Refund policy</a>.</p>
        @endif
    </div>
</div>
