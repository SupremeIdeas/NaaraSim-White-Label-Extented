{{-- Connectivity Analytics blueprint Part A §2.5 — Home hero. Dependency-free
     SVG sparklines only (Chart.js is reserved for My Line's richer charts);
     real data-usage + wallet-flow figures, never invented. Only shown once an
     account has at least one line — nothing real to plot before that. --}}
@if ($showAnalyticsHero)
    <a href="{{ route('numbers.lines') }}" wire:navigate
       class="group mb-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition group-hover:border-primary/40 group-hover:shadow-md dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="signal" class="h-4 w-4" /> Data used this week
            </p>
            <p class="mt-2 font-display text-2xl font-bold text-slate-900 dark:text-slate-100">
                @if ($weeklyUsageMb >= 1024)
                    {{ number_format($weeklyUsageMb / 1024, 1) }} GB
                @else
                    {{ number_format($weeklyUsageMb, 0) }} MB
                @endif
            </p>
            <div class="mt-3">
                @if ($hasUsageData)
                    <svg viewBox="0 0 200 48" class="h-12 w-full" role="img" aria-label="Daily data usage, last 7 days" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="nx-home-usage-fill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0A6E6E" stop-opacity="0.45" />
                                <stop offset="100%" stop-color="#0A6E6E" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <polygon points="0,44 {{ $usageSparkline }} 200,44" fill="url(#nx-home-usage-fill)" />
                        <polyline points="{{ $usageSparkline }}" fill="none" stroke="#0A6E6E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="dark:stroke-teal-300" />
                    </svg>
                @else
                    <div class="flex h-12 items-center text-xs text-slate-400 dark:text-slate-500">No usage yet this week.</div>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition group-hover:border-primary/40 group-hover:shadow-md dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <div class="flex items-center justify-between">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <x-icon name="wallet" class="h-4 w-4" /> Cash flow (30d)
                </p>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-primary dark:text-teal-300">
                    See more <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
            <div class="mt-2 flex items-center gap-3 text-[11px] font-medium">
                <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400"><span class="h-2 w-2 rounded-full bg-[#D4A017]"></span> Deposits</span>
                <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400"><span class="h-2 w-2 rounded-full bg-[#0A6E6E]"></span> Spend</span>
            </div>
            <div class="mt-2">
                @if ($hasFlowData)
                    <svg viewBox="0 0 200 48" class="h-12 w-full" role="img" aria-label="Deposits versus spend, last 30 days" preserveAspectRatio="none">
                        <polyline points="{{ $depositsSparkline }}" fill="none" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        <polyline points="{{ $spendSparkline }}" fill="none" stroke="#0A6E6E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="dark:stroke-teal-300" />
                    </svg>
                @else
                    <div class="flex h-12 items-center text-xs text-slate-400 dark:text-slate-500">No wallet activity yet this month.</div>
                @endif
            </div>
        </div>
    </a>
@endif
