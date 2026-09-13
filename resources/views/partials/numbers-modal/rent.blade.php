@php($isAny = $service === \App\Services\SMS\NumberRequest::SERVICE_ANY)
@if ($order)
    <div class="py-4 text-center">
        <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 text-green-600 dark:bg-green-950/50 dark:text-green-300">
            <x-icon name="badge-check" class="h-7 w-7" />
        </span>
        <p class="text-lg font-bold text-slate-900 dark:text-white">Rental reserved</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your rental number is ready — it receives SMS for its rental period.</p>
        <div class="mx-auto mt-4 max-w-xs rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
            <p class="text-[11px] uppercase tracking-wide text-slate-400">Your number</p>
            <p class="mt-0.5 select-all font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $order->phone_number }}</p>
        </div>
        <div class="mt-5 flex gap-2">
            <button type="button" wire:click="reset_" class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Rent another</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex-1 rounded-xl bg-primary py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-dark">View on dashboard</a>
        </div>
    </div>
@else
    {{-- Single Service vs Any Service --}}
    <div class="mb-4 inline-flex w-full rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
        <button type="button" wire:click="$set('service', 'whatsapp')"
                class="flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition {{ ! $isAny ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">Single service</button>
        <button type="button" @if ($fullRentAvailable) wire:click="$set('service', '{{ \App\Services\SMS\NumberRequest::SERVICE_ANY }}')" @else disabled @endif
                class="flex-1 rounded-full px-4 py-1.5 text-sm font-semibold transition disabled:opacity-40 {{ $isAny ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            Any service @unless ($fullRentAvailable)<span class="text-[10px]">(soon)</span>@endunless
        </button>
    </div>

    <div class="space-y-3">
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

        @unless ($isAny)
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
        @else
            <div class="rounded-2xl border border-primary/20 bg-primary/5 p-4 text-sm text-slate-600 dark:border-teal-400/20 dark:bg-teal-500/5 dark:text-slate-300">
                <x-icon name="badge-check" class="mr-1 inline h-4 w-4 text-primary dark:text-teal-300" />
                One number, <b>any service</b> — receives SMS from every service for the rental period.
            </div>
        @endunless
    </div>

    {{-- Duration — only US numbers (Getatext) offer real longer rentals; every
         other country is a fixed short-term rental, stated honestly. --}}
    @if ($isUsRental)
        <div class="mt-4">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Rental length</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($rentalDurations as $code => $label)
                    <button type="button" wire:click="$set('rentalDuration', '{{ $code }}')"
                            class="rounded-xl border px-2 py-2.5 text-sm font-semibold transition {{ $rentalDuration === $code ? 'border-primary bg-primary/5 text-primary dark:bg-primary/10 dark:text-teal-300' : 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300' }}">{{ $label }}</button>
                @endforeach
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model.live="autoRenew" class="rounded border-slate-300 text-primary focus:ring-primary/40">
                Auto-renew when it expires
            </label>
        </div>
    @else
        <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-white/5 dark:text-slate-400">
            <x-icon name="info" class="mr-1 inline h-3.5 w-3.5" /> A short-term rental — receives SMS for a fixed period. For a longer rental, choose a US number.
        </p>
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

    <div class="sticky bottom-0 -mx-5 mt-5 border-t border-slate-100 bg-white px-5 pt-4 dark:border-white/10 dark:bg-[#0D1B2A]">
        {{-- Taxes & fees (Prompt 10): no separate tax or fee is charged on top
             of the price shown — the line itself is the trust signal. --}}
        <div class="mb-2 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Taxes &amp; fees</span>
            <span class="font-medium text-slate-700 dark:text-slate-300">$0.00</span>
        </div>
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm text-slate-500 dark:text-slate-400">You pay</span>
            <span class="text-lg font-bold text-slate-900 dark:text-white">
                @if ($modalPrice !== null)${{ number_format($useCredits ? $modalPrice - $creditQuote['usd'] : $modalPrice, 2) }}@else <span class="text-sm font-medium text-slate-400">Priced at reservation</span>@endif
            </span>
        </div>
        <button type="button" wire:click="order" wire:loading.attr="disabled" wire:target="order"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <span wire:loading.remove wire:target="order"><x-icon name="hash" class="mr-1 inline h-4 w-4" /> Rent this number</span>
            <span wire:loading wire:target="order" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Reserving…</span>
        </button>
        <p class="mt-2 text-center text-[11px] text-slate-400">Charged from your wallet. Auto-refund if unavailable.</p>
    </div>
@endif
