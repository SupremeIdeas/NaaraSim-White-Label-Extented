<x-layouts.app :title="config('app.name') . ' — Foundation'">
    <main class="min-h-screen flex flex-col items-center justify-center gap-8 px-6">
        <div class="absolute top-5 right-5">
            <x-theme-toggle />
        </div>

        <div class="text-center max-w-xl">
            <p class="text-sm font-semibold uppercase tracking-widest text-accent-dark dark:text-accent">Supreme Ideas Agency</p>
            <h1 class="mt-2 text-4xl font-bold text-primary-dark dark:text-primary">NaaraSim</h1>
            <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">
                Stay Connected. No Borders. No Swaps.
            </p>
            <p class="mt-6 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-slate-400">
                Module 1 — Foundation is live. Toggle the theme in the top-right corner:
                the choice persists across reloads with no flash of the wrong theme.
            </p>
        </div>
    </main>
</x-layouts.app>
