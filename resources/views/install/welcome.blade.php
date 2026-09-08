<x-layouts.install title="Install — Welcome">
    <x-install-shell :step="1">
        <div class="text-center">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Welcome</h2>
            <div class="my-6 flex justify-center">
                <x-icon name="zap" class="h-12 w-12 text-accent-dark dark:text-accent" />
            </div>
            <h3 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100">Let’s start.</h3>
            <p class="mx-auto mt-4 max-w-sm text-slate-500 dark:text-slate-400">
                Thanks for choosing NaaraSim! You can install your platform in seconds — eSIMs, virtual numbers,
                and SMS verification in one app. Let’s get you started.
            </p>
            <a href="/install/requirements"
               class="mt-8 flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-3 font-semibold text-slate-900 transition-colors hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-100 dark:hover:bg-[#243352]">
                Next <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </div>
    </x-install-shell>
</x-layouts.install>
