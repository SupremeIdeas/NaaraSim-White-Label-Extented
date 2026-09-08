<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Call forwarding</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Send calls to your permanent NaaraSim number straight to your real phone —
        anywhere in the world. Give out one number, answer it on the phone in your pocket.
    </p>

    @if ($numbers->isEmpty())
        <div class="mt-6 flex flex-col items-center rounded-3xl border border-slate-200 nx-glass-tile px-6 py-12 text-center dark:border-white/10">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="phone-forwarded" class="h-8 w-8" />
            </span>
            <h2 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Forwarding needs a Naara Line</h2>
            <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                Get a permanent voice number, then send its calls to the phone in your pocket — anywhere in the world.
            </p>
            <a href="{{ route('numbers', ['modal' => 'line']) }}" wire:navigate
               class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">
                <x-icon name="plus" class="h-4 w-4" /> Get a Naara Line
            </a>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($numbers as $number)
                @php($rule = $rules[$number->id] ?? null)
                <div class="rounded-2xl border border-slate-200 nx-glass-tile p-5 shadow-sm dark:border-[var(--brand-card-border-dark)]" wire:key="num-{{ $number->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20"><x-icon name="phone" class="h-4 w-4" /></span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $number->phone_number }}</p>
                                @if ($rule && $rule->status === 'active')
                                    <p class="text-xs text-green-600 dark:text-green-400">Forwarding to {{ $rule->forward_to_number }}</p>
                                @else
                                    <p class="text-xs text-slate-400">Not forwarding</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($rule && $rule->status === 'active')
                                <button type="button" wire:click="disable({{ $rule->id }})" wire:confirm="Turn off call forwarding for this number?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300">Turn off</button>
                            @endif
                            <button type="button" wire:click="edit({{ $number->id }})"
                                    class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">{{ $rule && $rule->status === 'active' ? 'Edit' : 'Set up' }}</button>
                        </div>
                    </div>

                    @if ($numberId === $number->id)
                        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-[#243352]">
                            @if ($error)
                                <div class="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
                            @endif
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Forward calls to</label>
                                    <input type="tel" wire:model="forwardTo" placeholder="+2348012345678"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                                    @error('forwardTo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">No-answer backup (optional)</label>
                                    <input type="tel" wire:model="fallback" placeholder="+2348098765432"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                                    @error('fallback') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                                    class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                                <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
                                <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
                                Turn on forwarding
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
