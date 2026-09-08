{{-- Slim connectivity summary (Theme Batch 2 §2, extracted verbatim). Only for
     an account that already has lines (@if hasAny). --}}
@if ($hasAny)
    <a href="{{ route('numbers.lines') }}" wire:navigate
       class="group block rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <div class="flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="signal" class="h-4 w-4" /> My Lines
            </h2>
            <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                Manage all <x-icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
            </span>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $esimActiveCount }}</div>
                <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Active eSIMs</div>
            </div>
            <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $numberActiveCount }}</div>
                <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Active numbers</div>
            </div>
            <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                <div class="text-2xl font-bold text-slate-400 dark:text-slate-500">{{ $archivedCount }}</div>
                <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Archived</div>
            </div>
        </div>
    </a>
@endif
