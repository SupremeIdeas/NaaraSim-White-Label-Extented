<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Health Monitor</h1>
    <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">Live provider health at a glance — the same data the System Health panel reports.</p>

    @if ($task)
        <div @class([
            'mb-5 flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm',
            'border-green-200 bg-green-50 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300' => ! $task['overdue'],
            'border-red-300 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300' => $task['overdue'],
        ])>
            <x-icon name="{{ $task['overdue'] ? 'info' : 'check' }}" class="h-4 w-4" />
            <span>Provider health-check {{ $task['overdue'] ? 'is OVERDUE — the cron may not be firing' : 'last ran '.($task['ago'] ?? 'recently') }}. <a href="{{ route('admin.system-health') }}" wire:navigate class="underline">System Health</a></span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($rows as $r)
            <a href="{{ route('admin.nci.provider', $r->provider_key) }}" wire:navigate wire:key="hm-{{ $r->provider_key }}"
               class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-primary/40 hover:shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center justify-between">
                    <span class="font-semibold capitalize text-slate-800 dark:text-slate-100">{{ $r->provider_key }}</span>
                    <span class="h-3 w-3 rounded-full {{ ['ok' => 'bg-green-500', 'low' => 'bg-amber-500', 'down' => 'bg-red-500'][$r->status] ?? 'bg-slate-300 dark:bg-white/20' }}"></span>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <x-nci.pill kind="status" :value="$r->status" />
                    <x-nci.pill kind="circuit" :value="$r->circuit_breaker_state" />
                </div>
                <p class="mt-2 text-[11px] text-slate-400">{{ $r->last_checked_at?->diffForHumans() ?? 'never checked' }}</p>
            </a>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060]">No providers yet — fills after the first health-check cycle.</p>
        @endforelse
    </div>
</div>
