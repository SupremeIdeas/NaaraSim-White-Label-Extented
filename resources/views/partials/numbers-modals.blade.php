{{--
    Product modals (Numbers V6 §0) — pop like the mobile "More" sheet: a bottom
    sheet on mobile, a centered dialog on desktop. Livewire-driven ($modal), URL-
    reflected (?modal=), wired to the shared Country/Service pickers. GSAP-free,
    reduced-motion-safe transitions via Alpine.

    Dedicated-page mode (owner request, 2026-09-08, $pageMode from the admin's
    per-card display_mode — see App\Support\NumbersBento::isPageMode()): the
    EXACT same header + content include as the modal, just without the fixed
    overlay/backdrop/focus-trap chrome, laid out in-flow as a normal page
    section instead — so verify/rent/line's actual step logic, pickers, and
    content partial never fork between the two modes, only the chrome around
    them does.
--}}
@if ($modal !== '')
    @if ($pageMode ?? false)
        <div role="region" aria-label="{{ ucfirst($modal) }}"
             class="mt-2 flex max-h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0D1B2A]">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-teal-500/15 dark:text-teal-300">
                        <x-icon :name="['verify' => 'shield-check', 'rent' => 'hash', 'line' => 'phone'][$modal] ?? 'phone'" class="h-4 w-4" gradient />
                    </span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ ['verify' => 'Naara Verify', 'rent' => 'Naara Rent', 'line' => 'Naara Line'][$modal] ?? 'Numbers' }}
                    </h2>
                </div>
                <button type="button" wire:click="closeModal" aria-label="Back to Numbers"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10">
                    {{-- No dedicated back-arrow icon in the sprite — the existing
                         chevron-right, rotated, reads identically. --}}
                    <x-icon name="chevron-right" class="h-5 w-5 rotate-180" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-6">
                @include('partials.numbers-modal.'.$modal)
            </div>
        </div>
    @else
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
             x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeModal()"
             role="dialog" aria-modal="true" aria-label="{{ ucfirst($modal) }}">
            <div class="absolute inset-0 bg-black/60" wire:click="closeModal"></div>

            <div x-show="true" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-8 opacity-0 sm:scale-95"
                 x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                 class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">

                {{-- Grab handle (mobile) --}}
                <div class="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>

                {{-- Header --}}
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-teal-500/15 dark:text-teal-300">
                            <x-icon :name="['verify' => 'shield-check', 'rent' => 'hash', 'line' => 'phone'][$modal] ?? 'phone'" class="h-4 w-4" gradient />
                        </span>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ ['verify' => 'Naara Verify', 'rent' => 'Naara Rent', 'line' => 'Naara Line'][$modal] ?? 'Numbers' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="closeModal" aria-label="Close"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-6">
                    @include('partials.numbers-modal.'.$modal)
                </div>
            </div>
        </div>
    @endif
@endif
