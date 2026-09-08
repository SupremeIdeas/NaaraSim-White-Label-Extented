<div class="mx-auto max-w-lg">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Data estimator</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">A rough guide to how much data you’ll need — so you buy the right plan, not too much or too little.</p>

    <div class="space-y-5 rounded-2xl border border-slate-200 nx-glass-tile p-6 dark:border-[var(--brand-card-border-dark)]">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">How will you use it?</label>
            <div class="space-y-2">
                @foreach ($profiles as $key => $p)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200">
                        <input type="radio" wire:model.live="profile" value="{{ $key }}" class="text-primary"> {{ $p['label'] }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">How many days? <span class="font-bold text-primary">{{ $days }}</span></label>
            <input type="range" min="1" max="60" wire:model.live="days" class="w-full accent-primary">
        </div>

        <div class="rounded-xl bg-primary/10 p-5 text-center dark:bg-primary/20">
            <p class="text-sm text-slate-600 dark:text-slate-300">You’ll likely need about</p>
            <p class="my-1 text-3xl font-bold text-primary-dark dark:text-primary">{{ $estimate['gb'] }} GB</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">for {{ $days }} day{{ $days > 1 ? 's' : '' }} of {{ \Illuminate\Support\Str::of($estimate['label'])->before(' —') }} use</p>
        </div>

        <a href="{{ route('catalogue') }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white hover:bg-primary-dark">
            <x-icon name="globe" class="h-5 w-5" /> Find a plan
        </a>
        <p class="text-center text-xs text-slate-400 dark:text-slate-500">Estimates only — real usage varies with apps and video quality.</p>
    </div>
</div>
