{{-- Floating "My Journey" launcher (owner request) — same size + minimize
     behavior as the NaaraSim Wizard, positioned just in front of it (the
     slot directly above Wizard) for fast, on-demand navigation to activity
     progress + rewards without going through the menu. --}}
<div class="fixed bottom-40 right-4 z-50 print:hidden lg:bottom-24"
     wire:key="naara-journey-launcher"
     x-data="{ jlHidden: localStorage.getItem('nx_journey_hidden') === '1' }"
     x-effect="localStorage.setItem('nx_journey_hidden', jlHidden ? '1' : '0')">

    {{-- Minimised state: collapses to the same tiny restore bubble as the Wizard. --}}
    <button type="button" x-show="jlHidden" x-cloak @click="jlHidden = false"
            aria-label="Show the My Journey shortcut"
            class="flex h-11 w-11 items-center justify-center rounded-full bg-white p-px shadow-lg shadow-primary/20 ring-1 ring-primary/20 transition hover:shadow-primary/30 dark:bg-[#101d33] dark:ring-primary/30">
        <span class="flex h-full w-full items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
            <x-icon name="star" class="h-5 w-5" />
        </span>
    </button>

    {{-- Full launcher (hidden while minimised). --}}
    <div x-show="!jlHidden" class="relative">
        <button type="button" @click="jlHidden = true" aria-label="Hide the My Journey shortcut"
                class="absolute -left-1.5 -top-1.5 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-slate-700/90 text-white shadow ring-1 ring-white/50 transition hover:bg-slate-900 dark:bg-slate-200/90 dark:text-slate-800 dark:hover:bg-white">
            <x-icon name="x" class="h-3 w-3" />
        </button>

        <a href="{{ route('journey') }}" wire:navigate
           aria-label="Open My Journey — activity progress and rewards"
           class="group relative flex items-center gap-2 rounded-full bg-white p-px pr-3.5 shadow-lg shadow-primary/15 transition hover:shadow-primary/25 dark:bg-[#101d33]">
            <span class="relative flex items-center gap-2 rounded-full bg-white py-2.5 pl-2.5 dark:bg-[#101d33]">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon name="star" class="h-4 w-4" />
                </span>
                <span class="hidden pr-0.5 text-sm font-semibold text-slate-800 sm:inline dark:text-white">My Journey</span>
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-accent/15 px-2 py-1 text-xs font-bold text-accent-dark dark:bg-accent/20 dark:text-accent">
                <x-naara-coin class="h-3.5 w-3.5" /> {{ number_format($creditsBalance, 0) }}
            </span>
        </a>
    </div>
</div>
