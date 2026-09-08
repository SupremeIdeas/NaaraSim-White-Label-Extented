<div class="mx-auto max-w-3xl" x-data="{ term: '' }" @search.window="term = $event.detail.term">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">UI Kit</h1>
    <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">The reusable, themed, accessible components (Section 31). Everything here is keyboard-usable and dark-mode ready.</p>

    <div class="space-y-6">
        {{-- Star rating --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Star rating</h2>
            <div class="flex flex-wrap items-center gap-6">
                <div>
                    <p class="mb-1 text-xs text-slate-400">Display (4.5)</p>
                    <x-ui.star-rating :value="4.5" />
                </div>
                <div>
                    <p class="mb-1 text-xs text-slate-400">Interactive (click / arrow keys)</p>
                    <x-ui.star-rating :value="$demoRating" :readonly="false" wire="demoRating" size="h-7 w-7" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You picked: <span class="font-semibold">{{ $demoRating }}</span></p>
                </div>
            </div>
        </section>

        {{-- Modal engine --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Modal engine (the only one)</h2>
            <button type="button" x-data
                    @click="$dispatch('open-modal', { name: 'demo' })"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="help-circle" class="h-4 w-4" /> Open dialog
            </button>
            <p class="mt-2 text-xs text-slate-400">Focus is trapped inside; ESC or backdrop closes it.</p>

            <x-ui.modal name="demo" title="Demo dialog" max-width="md">
                <p class="text-sm text-slate-600 dark:text-slate-300">This dialog uses the shared engine. Tab cycles only these controls; ESC closes.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" x-data @click="$dispatch('close-modal')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-[#2D4060] dark:text-slate-200">Cancel</button>
                    <button type="button" x-data @click="$dispatch('close-modal')" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white">Confirm</button>
                </div>
            </x-ui.modal>
        </section>

        {{-- Countdown --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Server-anchored countdown</h2>
            <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                <x-ui.countdown :until="$countdownUntil" />
            </div>
            <p class="mt-2 text-xs text-slate-400">Anchored to server time — a wrong device clock can’t speed it up or slow it down.</p>
        </section>

        {{-- Search --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Search (debounced)</h2>
            <x-ui.search placeholder="Type to search…" class="max-w-sm" />
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Debounced term: <span class="font-mono" x-text="term || '—'"></span></p>
        </section>

        {{-- Theme toggle --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Theme toggle</h2>
            <div class="flex items-center gap-3">
                <x-theme-toggle />
                <span class="text-sm text-slate-500 dark:text-slate-400">Flips light / dark with no flash.</span>
            </div>
        </section>

        {{-- Branded elements (Module 32 — adapted from the hand-picked Uiverse set) --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840] lg:col-span-2">
            <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Branded elements</h2>
            <p class="mb-5 text-xs text-slate-400">Adapted from the hand-picked Uiverse library — re-coloured to the brand, dark-mode ready, no third-party runtime.</p>

            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.btn variant="primary" type="button" icon="badge-check">Primary action</x-ui.btn>
                    <x-ui.btn variant="gold" type="button">Gold highlight</x-ui.btn>
                    <x-ui.btn variant="ghost" type="button">Ghost</x-ui.btn>
                    <x-ui.btn variant="danger" type="button">Danger</x-ui.btn>
                </div>

                {{-- Component-library batch 2 button variants. --}}
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="nx-btn nx-btn--glow">Border glow</button>
                    <button type="button" class="nx-btn nx-btn--get-started">
                        Get started <x-icon name="chevron-right" class="nx-btn__arrow h-4 w-4" />
                    </button>
                    <button type="button" class="nx-btn nx-btn--premium">
                        <x-icon name="zap" class="h-4 w-4" /> Unlock Pro
                    </button>
                    <button type="button" class="nx-btn nx-btn--pill-reveal">
                        <span class="nx-btn__stack">
                            <span class="nx-btn__face">Learn more</span>
                            <span class="nx-btn__reveal">Let's go →</span>
                        </span>
                    </button>
                    <button type="button" class="nx-btn nx-btn--danger-confirm" wire:confirm="This cannot be undone. Continue?">
                        <x-icon name="trash" class="h-4 w-4" /> Delete account
                    </button>
                    <x-ui.icon-button icon="edit" label="Edit" />
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <x-ui.switch checked label="Demo switch" /> Switch
                    </label>
                    <label class="flex items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <x-ui.checkbox checked label="Demo checkbox" /> Checkbox
                    </label>
                    <x-ui.tag variant="live">Active</x-ui.tag>
                    <x-ui.tag variant="soon">Coming Soon</x-ui.tag>
                    <x-ui.tag variant="gold">Featured</x-ui.tag>
                    <x-ui.loader size="1.75rem" />
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.alert variant="info" title="Heads up" icon="info">Informational alert card.</x-ui.alert>
                    <x-ui.alert variant="warning" title="Careful" icon="shield">Warning alert card.</x-ui.alert>
                    <x-ui.alert variant="danger" title="Problem" icon="x">Danger alert card.</x-ui.alert>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-2">
                        <x-ui.skeleton class="h-4 w-3/4" />
                        <x-ui.skeleton class="h-4 w-1/2" />
                        <x-ui.skeleton class="h-24 w-full" />
                    </div>
                    <x-ui.upload label="Click or drop a file here" hint="Skeletons load, uploads drop." />
                </div>

                <button type="button"
                        x-on:click="window.dispatchEvent(new CustomEvent('nx-toast', { detail: { type: 'success', message: 'Toast test — it works.' } }))"
                        class="text-xs font-medium text-primary hover:underline">
                    Fire a test toast
                </button>
            </div>
        </section>
    </div>
</div>
