<div class="mx-auto max-w-2xl"
     x-data="{
        source: @entangle('ngn_rate_source').live,
        manual: @entangle('manual_ngn_rate').live,
        markup: @entangle('ngn_rate_markup_pct').live,
        get manualPreview() { return Math.round(this.manual * (1 + this.markup / 100)); },
     }">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Exchange rate — Nigerian Naira</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">This one rate drives every NGN price shown to customers <em>and</em> every NGN payout, so they never disagree. Set it to reflect the <strong>parallel (market) rate</strong> your customers actually transact at — not the official bank rate — so naira prices feel right.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    {{-- Current live rate (as resolved on the server right now). --}}
    <div class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 p-5 dark:border-primary/30 dark:bg-primary/10">
        <p class="text-xs font-semibold uppercase tracking-wide text-primary dark:text-teal-300">Current live rate</p>
        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">$1 = ₦{{ number_format($resolvedRate, 0) }}</p>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">e.g. $10 ≈ ₦{{ number_format($resolvedRate * 10, 0) }} · $50 ≈ ₦{{ number_format($resolvedRate * 50, 0) }}</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        {{-- Source --}}
        <label class="block text-sm font-semibold text-slate-900 dark:text-white">Rate source</label>
        <div class="mt-2 space-y-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                   :class="source === 'auto' ? 'border-primary bg-primary/5 dark:border-primary/50 dark:bg-primary/10' : 'border-slate-200 dark:border-[#2D4060]'">
                <input type="radio" value="auto" x-model="source" class="mt-1 accent-primary">
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Automatic (official rate) + markup</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Tracks Airalo's live interbank rate and adds your parallel-market markup below. Good if you want it to move automatically.</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                   :class="source === 'manual' ? 'border-primary bg-primary/5 dark:border-primary/50 dark:bg-primary/10' : 'border-slate-200 dark:border-[#2D4060]'">
                <input type="radio" value="manual" x-model="source" class="mt-1 accent-primary">
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Manual (exact parallel rate)</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">You type the exact NGN-per-USD rate. Set the markup to 0% to use it as-is. Best if you follow the market rate yourself.</span>
                </span>
            </label>
        </div>

        {{-- Manual rate --}}
        <div class="mt-5" x-show="source === 'manual'" x-cloak>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">NGN per $1 (parallel rate)</label>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-slate-400">₦</span>
                <input type="number" min="100" max="100000" step="1" x-model.number="manual"
                       class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            @error('manual_ngn_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- In auto mode, the manual rate is the safety fallback if the feed is down. --}}
        <div class="mt-5" x-show="source === 'auto'" x-cloak>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Fallback rate (if the live feed is unreachable)</label>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-slate-400">₦</span>
                <input type="number" min="100" max="100000" step="1" x-model.number="manual"
                       class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
        </div>

        {{-- Markup --}}
        <div class="mt-5">
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Parallel-market markup</label>
                <span class="text-sm font-semibold text-primary dark:text-teal-300" x-text="markup + '%'"></span>
            </div>
            <input type="range" min="0" max="50" step="0.5" x-model.number="markup" class="mt-2 w-full accent-primary">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Lifts the base rate to bridge the gap between the official and parallel (market) rate. In manual mode leave this at 0% if you already typed the market rate.</p>
            @error('ngn_rate_markup_pct') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Manual-mode client-side preview (auto base is server-side, shown above). --}}
        <div class="mt-5 rounded-lg bg-slate-50 px-4 py-3 dark:bg-white/5" x-show="source === 'manual'" x-cloak>
            <p class="text-xs text-slate-500 dark:text-slate-400">After markup this will apply:</p>
            <p class="text-lg font-bold text-slate-900 dark:text-white">$1 = ₦<span x-text="manualPreview.toLocaleString()"></span></p>
            <p class="text-xs text-slate-400">Saves as the live rate for prices and payouts.</p>
        </div>

        <div class="mt-6">
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save rate</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
        <p class="font-semibold">Why there's no fully-automatic parallel rate</p>
        <p class="mt-1">The built-in converter is live, but public FX feeds only publish the <strong>official</strong> rate — there is no free, reliable, terms-clean feed for Nigeria's parallel rate. So you steer it here: either follow the market rate manually, or set a markup that keeps the automatic official rate close to the street rate. Review it whenever the gap moves.</p>
    </div>
</div>
