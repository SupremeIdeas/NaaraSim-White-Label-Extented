<x-layouts.auth title="Forgot password — NaaraSim" heading="Reset your password" subheading="We'll email you a secure link to set a new one.">
            <form method="POST" action="/forgot-password"
                  class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @csrf
                @if (session('status'))
                    <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                        <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('status') }}</span>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Enter your email and we'll send you a secure link to set a new password.
                </p>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="send" class="h-5 w-5" /> Email me a reset link
                </button>
                <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                    Remembered it? <a href="/login" class="font-semibold text-primary hover:underline">Back to sign in</a>
                </p>
            </form>
</x-layouts.auth>
