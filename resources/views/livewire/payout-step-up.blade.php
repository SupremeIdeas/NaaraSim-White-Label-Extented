<div>
    @if ($needed)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10" role="group" aria-labelledby="stepup-title">
            <div class="flex items-start gap-3">
                <x-icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
                <div class="min-w-0 flex-1 text-sm">
                    <p id="stepup-title" class="font-semibold text-amber-900 dark:text-amber-200">{{ __('payouts.step_up.title') }}</p>
                    <p class="mt-0.5 text-amber-800 dark:text-amber-300/90">{{ __('payouts.step_up.body') }}</p>

                    @if ($error)<p class="mt-2 text-red-700 dark:text-red-300" role="alert">{{ $error }}</p>@endif
                    @if ($message)<p class="mt-2 text-emerald-700 dark:text-emerald-300" role="status">{{ $message }}</p>@endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if (! $authenticator)
                            <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                                class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 disabled:opacity-50 dark:border-amber-500/40 dark:bg-slate-900 dark:text-amber-200">{{ __('payouts.step_up.send') }}</button>
                        @endif
                        <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8" wire:model="code" wire:keydown.enter="verify"
                            placeholder="{{ __('payouts.step_up.enter') }}" aria-label="{{ __('payouts.step_up.enter') }}"
                            class="w-44 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm text-slate-900 dark:border-amber-500/40 dark:bg-slate-900 dark:text-slate-100">
                        <button type="button" wire:click="verify" wire:loading.attr="disabled" wire:target="verify"
                            class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">{{ __('payouts.step_up.verify') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($message)
        <p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" role="status">{{ $message }}</p>
    @endif
</div>
