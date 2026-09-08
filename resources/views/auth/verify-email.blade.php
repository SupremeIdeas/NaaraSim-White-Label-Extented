<x-layouts.auth title="Confirm your email — NaaraSim" heading="Confirm your email" subheading="One quick step to activate your account.">
            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                @if (session('status') == 'verification-link-sent')
                    <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                        <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>A fresh confirmation link is on its way to your inbox.</span>
                    </div>
                @endif
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Thanks for signing up. Please click the link we emailed you to activate your account.
                    Didn't get it? Send it again below.
                </p>
                <form method="POST" action="/email/verification-notification">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                        <x-icon name="send" class="h-5 w-5" /> Resend confirmation email
                    </button>
                </form>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit" class="w-full text-center text-sm text-slate-500 hover:text-primary dark:text-slate-400">
                        Sign out
                    </button>
                </form>
            </div>
</x-layouts.auth>
