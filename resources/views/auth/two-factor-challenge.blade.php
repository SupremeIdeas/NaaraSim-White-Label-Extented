<x-layouts.auth title="Two-factor — NaaraSim" heading="Two-factor authentication"
    subheading="Enter the 6-digit code from your authenticator app (Google Authenticator, Authy, 1Password…).">
    <div x-data="{ recovery: false }"
         class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="/two-factor-challenge" class="space-y-4">
            @csrf
            {{-- Authenticator code --}}
            <div x-show="! recovery">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Authentication code</label>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                       x-bind:autofocus="! recovery" placeholder="123456"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-lg tracking-[0.4em] text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>

            {{-- Recovery code --}}
            <div x-show="recovery" x-cloak>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Recovery code</label>
                <input type="text" name="recovery_code" autocomplete="off"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>

            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                <x-icon name="shield-check" class="h-5 w-5" /> Verify
            </button>
        </form>

        <button type="button" @click="recovery = ! recovery"
                class="w-full text-center text-sm font-medium text-primary hover:underline">
            <span x-show="! recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use an authenticator code instead</span>
        </button>
    </div>
</x-layouts.auth>
