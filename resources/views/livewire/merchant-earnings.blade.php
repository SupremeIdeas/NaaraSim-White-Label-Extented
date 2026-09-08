<div class="mx-auto max-w-3xl px-4 py-6">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('merchant.dashboard') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> My Storefront
            </a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Earnings</h1>
        </div>
        <div class="inline-flex rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            @foreach ([30 => '30d', 90 => '90d', 365 => '1y'] as $days => $label)
                <button wire:click="setPeriod({{ $days }})" class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $period === $days ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Earned · last {{ $period }} days</p>
        <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">${{ number_format($total, 2) }}</p>

        {{-- Time-series (dependency-free CSS bars). --}}
        @php($max = max(0.01, collect($series)->max()))
        <div class="mt-5 flex h-32 items-end gap-px overflow-hidden">
            @foreach ($series as $day => $amt)
                <div class="group relative flex-1" style="height: 100%">
                    <div class="absolute bottom-0 w-full rounded-t bg-primary/70 transition-all hover:bg-primary" style="height: {{ max(1, (int) round($amt / $max * 100)) }}%" title="{{ $day }}: ${{ number_format($amt, 2) }}"></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        {{-- Breakdown by product line. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">By product line</h2>
            @php($sourceMax = max(0.01, $bySource->max() ?? 0))
            @forelse ($bySource as $source => $amt)
                <div class="mb-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-300">{{ ucwords(str_replace('_', ' ', $source)) }}</span>
                        <span class="tabular-nums text-slate-500 dark:text-slate-400">${{ number_format($amt, 2) }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary" style="width: {{ (int) round($amt / $sourceMax * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No earnings in this window yet.</p>
            @endforelse
        </div>

        {{-- Top customers. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Top customers</h2>
            @forelse ($top as $row)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="truncate text-slate-700 dark:text-slate-200">{{ $row['name'] }}</span>
                    <span class="tabular-nums font-semibold text-slate-900 dark:text-white">${{ number_format($row['amount'], 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No customer earnings yet.</p>
            @endforelse
        </div>
    </div>
</div>
