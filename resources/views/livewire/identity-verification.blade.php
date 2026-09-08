<div class="mx-auto max-w-xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Verify your identity</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        A one-time check (ID + selfie) that unlocks cash withdrawals. Your ID number
        is sent straight to our verification partner — we never store it.
    </p>

    @if ($verified)
        <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/40">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/60 dark:text-green-300">
                    <x-icon name="badge-check" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-semibold text-green-800 dark:text-green-300">Verified</p>
                    <p class="text-sm text-green-700/80 dark:text-green-400/80">You're all set to withdraw earnings.</p>
                </div>
            </div>
        </div>
    @elseif ($attempt && $attempt->status === 'pending')
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-950/40">
            <p class="font-semibold text-amber-800 dark:text-amber-300">Verification in progress</p>
            <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-400/80">
                We're reviewing your details. This usually takes a few minutes — you'll be
                notified as soon as it's done.
            </p>
        </div>
    @else
        @if ($attempt && $attempt->status === 'rejected')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                Your last attempt couldn't be verified{{ $attempt->reason ? ': '.$attempt->reason : '.' }} Please check your details and try again.
            </div>
        @endif

        <form wire:submit="submit" class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Country</label>
                    <select wire:model="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        <option value="NG">Nigeria</option>
                        <option value="GH">Ghana</option>
                        <option value="KE">Kenya</option>
                        <option value="ZA">South Africa</option>
                    </select>
                    @error('country') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">ID type</label>
                    <select wire:model="idType" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        <option value="BVN">BVN</option>
                        <option value="NIN">NIN</option>
                        <option value="PASSPORT">Passport</option>
                        <option value="DRIVERS_LICENSE">Driver's licence</option>
                    </select>
                    @error('idType') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">ID number</label>
                <input type="text" wire:model="idNumber" autocomplete="off"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                @error('idNumber') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="shield" wire:loading.remove wire:target="submit" class="h-4 w-4" />
                <x-ui.spinner wire:loading wire:target="submit" class="h-4 w-4" />
                Submit for verification
            </button>
        </form>
    @endif
</div>
