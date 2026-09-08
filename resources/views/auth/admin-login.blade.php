<x-layouts.auth title="Admin sign-in — NaaraSim" heading="Administrator access"
    subheading="Sign in to the NaaraSim control panel.">
    <form method="POST" action="/login"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        @csrf
        <div class="flex items-center gap-2 rounded-lg bg-primary/5 p-3 text-xs font-medium text-primary dark:bg-primary/10 dark:text-teal-300">
            <x-icon name="shield" class="h-4 w-4 shrink-0" /> Secure admin area — access is logged.
        </div>
        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
            </div>
        @endif
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
            <x-ui.password name="password" required
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" name="remember" class="rounded text-primary"> Keep me signed in on this device
        </label>
        <x-turnstile />
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
            <x-icon name="shield-check" class="h-5 w-5" /> Sign in to admin
        </button>

        <div class="mt-4 flex items-center justify-center gap-3 text-xs">
            <a href="{{ route('password.request') }}" class="font-medium text-slate-500 hover:text-primary dark:text-slate-400">Reset via email</a>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <a href="{{ route('admin.recover') }}" class="font-medium text-slate-500 hover:text-primary dark:text-slate-400">Recovery questions</a>
        </div>
    </form>
</x-layouts.auth>
