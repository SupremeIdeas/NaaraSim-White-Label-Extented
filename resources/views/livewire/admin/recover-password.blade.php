<div class="mx-auto w-full max-w-md px-4 py-10">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h1 class="text-lg font-bold text-slate-900 dark:text-white">Recover admin access</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Answer your recovery questions to set a new password.</p>

        @if ($error)
            <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
        @endif

        @if ($step === 1)
            <form wire:submit="lookup" class="mt-5 space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Admin email</label>
                    <input type="email" wire:model="email" autocomplete="username" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Continue</button>
            </form>
        @elseif ($step === 2)
            <form wire:submit="verify" class="mt-5 space-y-4">
                @foreach ($prompts as $q)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ $q }}</label>
                        <input type="text" wire:model="answers.{{ $q }}" autocomplete="off" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                @endforeach
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Verify answers</button>
            </form>
        @else
            <form wire:submit="resetPassword" class="mt-5 space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">New password</label>
                    <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Confirm new password</label>
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Set new password</button>
            </form>
        @endif

        <div class="mt-5 text-center">
            <a href="{{ route('admin.login') }}" class="text-xs font-medium text-primary hover:underline">Back to sign in</a>
        </div>
    </div>
</div>
