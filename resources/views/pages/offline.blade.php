<x-layouts.marketing title="Offline">
    <div class="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center px-6 text-center">
        <span class="mb-4 rounded-2xl bg-primary/10 p-4 text-primary dark:text-teal-300">
            <x-icon name="wifi" class="h-8 w-8" />
        </span>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">You're offline</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-300">
            NaaraSim needs a connection for this page. Check your network and try again — your data and wallet are safe.
        </p>
        <button type="button" onclick="location.reload()"
            class="mt-6 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark">
            Retry
        </button>
    </div>
</x-layouts.marketing>
