<x-layouts.auth title="Set a new password — NaaraSim" heading="Choose a new password" subheading="Pick something strong you'll remember.">
            <form method="POST" action="/reset-password"
                  class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">New password</label>
                    <x-ui.password name="password" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Confirm new password</label>
                    <x-ui.password name="password_confirmation" required
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                </div>
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="shield-check" class="h-5 w-5" /> Save new password
                </button>
            </form>
</x-layouts.auth>
