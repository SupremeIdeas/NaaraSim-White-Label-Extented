<x-layouts.install title="Install — Done">
    <x-install-shell :step="4">
        <div class="text-center">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Application has been successfully installed.</p>
            <h2 class="mt-4 text-lg font-bold text-slate-900 dark:text-slate-100">Done!</h2>
            <div class="my-6 flex justify-center">
                <span class="flex h-20 w-20 items-center justify-center rounded-full border-2 border-green-500 text-green-500">
                    <x-icon name="check" class="h-10 w-10" />
                </span>
            </div>
            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100">Installation Completed.</h3>
            <p class="mx-auto mt-4 max-w-sm text-slate-500 dark:text-slate-400">
                You’ve completed the smart installation. Sign in to your admin panel with the default account below.
            </p>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5 text-left dark:border-[#2D4060] dark:bg-[#243352]">
                <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Default login credentials:</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Admin panel:
                    <span class="rounded bg-red-50 px-2 py-0.5 font-mono text-red-600 dark:bg-red-950/40 dark:text-red-300">/{{ $adminPath }}</span>
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Email:
                    <span class="rounded bg-red-50 px-2 py-0.5 font-mono text-red-600 dark:bg-red-950/40 dark:text-red-300">{{ $email }}</span>
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Password:
                    <span class="rounded bg-red-50 px-2 py-0.5 font-mono text-red-600 dark:bg-red-950/40 dark:text-red-300">{{ $password }}</span>
                </p>
                <p class="mt-3 flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-300">
                    <x-icon name="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    For security, change this password immediately after your first login — bookmark the
                    admin panel path above first, or you'll lose track of where it lives.
                </p>
            </div>

            {{-- Final step: the one cron entry that powers the platform. --}}
            <div class="mt-6">
                <x-cron-setup :hosting="$hosting ?? 'auto'" />
            </div>

            <a href="{{ $adminLoginUrl }}"
               class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white hover:bg-primary-dark">
                Go to admin login <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </div>
    </x-install-shell>
</x-layouts.install>
