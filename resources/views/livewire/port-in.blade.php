<div class="mx-auto max-w-2xl">
    <div class="mb-5">
        <a href="{{ route('numbers.lines') }}" wire:navigate
           class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> My Lines
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Bring your number to Naara</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Keep your existing US or Canada number and use it on Naara. Bringing a number in
            usually takes <span class="font-semibold text-slate-700 dark:text-slate-200">5–15 business days</span> —
            it's handled by the carriers, not instant. Nothing is charged until your number is live.
        </p>
    </div>

    {{-- Honest requirements note — a port-in fails if these don't match the losing
         carrier's records, so we ask for them up front. --}}
    <div class="mb-5 flex items-start gap-2 rounded-2xl bg-amber-50 p-4 text-xs leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Have your current carrier's <strong>account number</strong> and <strong>transfer PIN</strong> ready, and
            enter the <strong>name and address exactly as they appear on that account</strong>. A mismatch is the most
            common reason a transfer is rejected. Keep your old line active until the transfer completes.</span>
    </div>

    <form wire:submit="submit"
          class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Number to bring in</label>
                <input type="text" wire:model="phone_number" placeholder="+1 555 000 1234"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('phone_number') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Current account number</label>
                <input type="text" wire:model="account_number"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('account_number') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Transfer PIN</label>
                <input type="text" wire:model="pin"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('pin') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Account holder name</label>
                <input type="text" wire:model="billing_name"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('billing_name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Billing address on the account</label>
                <input type="text" wire:model="billing_address"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('billing_address') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Anything else we should know? <span class="text-slate-400">(optional)</span></label>
                <textarea wire:model="notes" rows="2"
                          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('notes') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark disabled:opacity-60 sm:w-auto">
            <x-icon name="phone-forwarded" class="h-4 w-4" />
            <span wire:loading.remove wire:target="submit">Submit port-in request</span>
            <span wire:loading wire:target="submit">Submitting…</span>
        </button>
    </form>

    {{-- The customer's own requests + live status. --}}
    @if ($requests->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Your port-in requests</h2>
            <div class="space-y-2">
                @foreach ($requests as $request)
                    @php($closed = ! $request->isOpen())
                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $request->phone_number }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">Requested {{ $request->created_at->format('M j, Y') }}</div>
                            @if ($request->status === \App\Models\PortInRequest::STATUS_REJECTED && $request->rejection_reason)
                                <div class="mt-0.5 text-xs text-red-600 dark:text-red-400">{{ $request->rejection_reason }}</div>
                            @endif
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $request->status === \App\Models\PortInRequest::STATUS_COMPLETED,
                            'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $request->status === \App\Models\PortInRequest::STATUS_REJECTED,
                            'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-primary' => ! $closed,
                        ])>
                            {{ \App\Models\PortInRequest::statusLabel($request->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
