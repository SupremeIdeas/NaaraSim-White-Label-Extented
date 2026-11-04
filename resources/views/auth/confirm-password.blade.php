<x-layouts.auth title="Confirm your password" heading="Confirm your password" subheading="This is a secure area. Please confirm your password to continue.">
            <form method="POST" action="{{ route('password.confirm.store') }}"
                  class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2A2E34] dark:bg-[#17191C]">
                @csrf
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                <div>
                    <label for="password" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
                    <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2A2E34] dark:bg-[#373a3f] dark:text-slate-100">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="shield-check" class="h-5 w-5" /> Confirm and continue
                </button>
                <p class="text-center text-sm text-slate-500 dark:text-slate-400"><a href="javascript:history.back()" class="font-semibold text-primary hover:underline">Go back</a></p>
            </form>
</x-layouts.auth>
