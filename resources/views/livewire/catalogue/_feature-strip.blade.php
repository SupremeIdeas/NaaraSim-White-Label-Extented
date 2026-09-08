{{-- Trust/feature strip (reference footer row): four honest platform promises,
     shown at the foot of the eSIM front. Static, brand-safe copy — no invented
     provider claims. One shared partial, identical across every theme. --}}
<div class="mt-8 grid grid-cols-2 gap-3 rounded-2xl border border-slate-200 nx-glass-tile p-4 shadow-sm dark:border-[var(--brand-card-border-dark)] sm:grid-cols-4">
    @foreach ([
        ['zap', 'Instant Activation', 'Online in seconds'],
        ['shield-check', 'Secure & Private', 'Your data, protected'],
        ['globe', '190+ Countries', 'Global coverage'],
        ['message-circle', '24/7 Support', "We're here anytime"],
    ] as [$icon, $title, $sub])
        <div class="flex items-start gap-2.5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="{{ $icon }}" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 dark:text-slate-100">{{ $title }}</p>
                <p class="text-[11px] leading-tight text-slate-500 dark:text-slate-400">{{ $sub }}</p>
            </div>
        </div>
    @endforeach
</div>
