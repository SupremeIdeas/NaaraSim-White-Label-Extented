<div class="mx-auto max-w-2xl px-4 py-16">
    @php($accent = $merchant->brand_color ?: '#0A6E6E')
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        {{-- Merchant hero (co-brand accent) --}}
        <div class="relative px-8 py-12 text-center"
             style="background: linear-gradient(135deg, {{ $accent }}14, transparent);">
            @if ($merchant->logo_url)
                <img src="{{ $merchant->logo_url }}" alt="{{ $merchant->business_name }}"
                     class="mx-auto h-24 w-24 rounded-2xl object-contain shadow-sm ring-1 ring-black/5 dark:ring-white/10">
            @else
                <span class="mx-auto flex h-24 w-24 items-center justify-center rounded-2xl text-3xl font-bold uppercase text-white shadow-sm"
                      style="background-color: {{ $accent }};">
                    {{ \Illuminate\Support\Str::of($merchant->business_name)->trim()->substr(0, 2) }}
                </span>
            @endif
            <h1 class="mt-6 font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $merchant->business_name }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Global eSIM data & phone numbers — 190+ countries, no swaps, no borders.
            </p>
        </div>

        {{-- Value + CTA --}}
        <div class="border-t border-slate-100 px-8 py-8 dark:border-[#243352]">
            <ul class="mx-auto max-w-md space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <li class="flex items-start gap-2"><x-icon name="globe" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Instant eSIM data plans for 190+ countries.</li>
                <li class="flex items-start gap-2"><x-icon name="hash" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Virtual & verification numbers in one app.</li>
                <li class="flex items-start gap-2"><x-icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Secure wallet, transparent pricing, live support.</li>
            </ul>

            <a href="{{ route('register') }}" wire:navigate
               class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
               style="background-color: {{ $accent }};">
                <x-icon name="zap" class="h-5 w-5" /> Create your account
            </a>
            <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">
                Already have an account? <a href="{{ route('login') }}" wire:navigate class="font-medium text-primary hover:underline">Sign in</a>
            </p>
        </div>

        {{-- Powered by NaaraSim — always present alongside the merchant brand. --}}
        <div class="flex items-center justify-center gap-1.5 border-t border-slate-100 bg-slate-50 py-4 text-xs text-slate-400 dark:border-[#243352] dark:bg-[#141F33] dark:text-slate-500">
            <x-icon name="signal" class="h-3.5 w-3.5" /> Powered by <span class="font-semibold text-slate-500 dark:text-slate-400">NaaraSim</span>
        </div>
    </div>
</div>
