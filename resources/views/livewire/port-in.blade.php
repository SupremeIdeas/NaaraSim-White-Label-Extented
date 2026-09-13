<div class="mx-auto max-w-2xl">
    <div class="mb-5">
        <a href="{{ route('numbers.lines') }}" wire:navigate
           class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> My Lines
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Bring your number to Naara</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Keep your existing US or Canada number and use it on Naara. First we check with the
            carrier that your number can actually be moved — then, if it can, you give us a few
            details and we handle the transfer. It usually takes
            <span class="font-semibold text-slate-700 dark:text-slate-200">5–15 business days</span>
            (it's the carriers' timeline, not instant), and nothing is charged until your number is live.
            Keep your current line active until it completes.
        </p>
    </div>

    {{-- Step 1 — the eligibility probe. Always shown; changing the number resets it. --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <form wire:submit="checkEligibility">
            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Which number do you want to bring in?</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" wire:model.live.debounce.500ms="phone_number" placeholder="+1 555 000 1234"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <button type="submit" wire:loading.attr="disabled" wire:target="checkEligibility"
                        class="shrink-0 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="checkEligibility">Check eligibility</span>
                    <span wire:loading wire:target="checkEligibility">Checking…</span>
                </button>
            </div>
            @error('phone_number') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>

        {{-- Not eligible → an honest sorry, never a promise we can't keep. --}}
        @if ($eligible === false)
            <div class="mt-4 flex items-start gap-2 rounded-2xl bg-amber-50 p-4 text-sm leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ $eligibilityMessage ?: 'Sorry — this number can’t be brought in right now.' }}</span>
            </div>
        @endif

        {{-- Eligible → confirm, then collect the carrier details. --}}
        @if ($eligible === true)
            <div class="mt-4 flex items-start gap-2 rounded-2xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Good news — this number can be brought to Naara. Fill in the details below to start the transfer.</span>
            </div>

            <div class="mt-4 flex items-start gap-2 rounded-2xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-600 dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-300">
                <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <span>Enter the account holder name and address <strong>exactly as they appear on your current
                    carrier account</strong>@if ($pinRequired), plus that account's <strong>number</strong> and
                    <strong>transfer PIN</strong>@endif. A mismatch is the most common reason a transfer is rejected.</span>
            </div>

            <form wire:submit="submit" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                        Current account number @unless ($pinRequired)<span class="text-slate-400">(if your carrier requires one)</span>@endunless
                    </label>
                    <input type="text" wire:model="account_number"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('account_number') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                        Transfer PIN @unless ($pinRequired)<span class="text-slate-400">(if required)</span>@endunless
                    </label>
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
                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark disabled:opacity-60 sm:w-auto">
                        <x-icon name="phone-forwarded" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="submit">Submit port-in request</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

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
