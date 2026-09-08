{{-- Premium wallet card (Theme Batch 2 §2, extracted verbatim). --}}
@if ($wallet)
    <a href="{{ route('wallet') }}"
       class="group relative mb-8 block overflow-hidden rounded-3xl bg-gradient-to-br from-primary via-primary-dark to-navy p-6 text-white shadow-xl shadow-primary/20 transition hover:shadow-2xl hover:shadow-primary/30 sm:p-7">
        <div class="pointer-events-none absolute -right-14 -top-14 h-48 w-48 rounded-full bg-accent/20 blur-3xl transition group-hover:bg-accent/30"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-10 h-44 w-44 rounded-full bg-white/10 blur-3xl"></div>

        <div class="relative flex items-start justify-between">
            <div>
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100">
                    <x-icon name="wallet" class="h-4 w-4" /> Wallet balance
                </p>
                <p class="mt-3 font-display text-4xl font-bold tracking-tight">
                    ${{ number_format((float) $wallet->usd_balance, 2) }}
                </p>
                {{-- Unified USD Wallet (Part B): usd_balance is the one spendable
                     balance — this is its live-rate NGN equivalent, never the
                     frozen (and now legacy) ngn_balance column. --}}
                <p class="mt-1 text-sm text-teal-100/90">≈ {{ app(\App\Services\Pricing\CurrencyService::class)->format((float) $wallet->usd_balance, 'NGN') }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur transition group-hover:bg-accent group-hover:text-navy">
                Top up <x-icon name="chevron-right" class="h-4 w-4" />
            </span>
        </div>
        <p class="relative mt-5 text-[11px] tracking-wide text-teal-100/70">{{ auth()->user()->name }} · {{ \App\Support\BrandSettings::name() }}</p>
    </a>
@endif
