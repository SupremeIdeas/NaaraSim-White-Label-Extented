{{-- Coupon marketing nudge (Theme Batch 2 §2, extracted verbatim). --}}
@if ($couponNudge)
    <div class="mb-6 overflow-hidden rounded-2xl border border-accent/30 bg-gradient-to-br from-accent/10 via-transparent to-primary/[0.06] p-5 dark:border-accent/40 dark:from-accent/15 dark:to-primary/10">
        <div class="flex flex-wrap items-center gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent-dark dark:bg-accent/25 dark:text-accent">
                <x-icon name="gift" class="h-6 w-6" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 dark:text-slate-100">{{ $couponNudge['title'] }}</p>
                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $couponNudge['message'] }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-lg border border-dashed border-accent/50 bg-white px-3 py-1.5 font-mono text-sm font-bold tracking-wider text-accent-dark dark:bg-[var(--brand-card-dark)] dark:text-accent">{{ $couponNudge['code'] }}</span>
                <a href="{{ route('catalogue') }}" wire:navigate class="nx-btn nx-btn--primary !px-4 !py-2 text-sm">
                    {{ $couponNudge['cta'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </div>
@endif
