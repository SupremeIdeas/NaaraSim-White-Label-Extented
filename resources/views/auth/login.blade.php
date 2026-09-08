<x-layouts.auth title="Sign in — NaaraSim" heading="Welcome back" subheading="Sign in to manage your eSIMs, numbers and wallet.">
    <form method="POST" action="/login"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        @csrf
        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
            </div>
        @endif
        <x-auth.social-buttons />
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
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="remember" class="rounded text-primary"> Remember me
            </label>
            <a href="/forgot-password" class="text-sm font-medium text-primary hover:underline">Forgot password?</a>
        </div>
        <x-turnstile />
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
            <x-icon name="shield-check" class="h-5 w-5" /> Sign in
        </button>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            New here? <a href="/register" class="font-semibold text-primary hover:underline">Create an account</a>
        </p>
    </form>
</x-layouts.auth>
