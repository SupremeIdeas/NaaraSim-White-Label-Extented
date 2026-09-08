<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Error log</h1>
        <div class="flex items-end gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Day</label>
                <input type="date" wire:model.live="date"
                       class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Severity</label>
                <select wire:model.live="severity" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">All</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                </select>
            </div>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="file-text" class="h-4 w-4" /> CSV
            </button>
            <button type="button" wire:click="exportJson" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="file-text" class="h-4 w-4" /> JSON
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Severity</th>
                    <th class="px-4 py-2 font-medium">Code</th>
                    <th class="px-4 py-2 font-medium">Message</th>
                    <th class="px-4 py-2 font-medium">When</th>
                </tr>
            </thead>
            <tbody wire:loading wire:target="date,severity,gotoPage,nextPage,previousPage" class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                <x-ui.skeleton-table-rows :cols="4" />
            </tbody>
            <tbody wire:loading.remove wire:target="date,severity,gotoPage,nextPage,previousPage" class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="text-slate-700 dark:text-slate-200">
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $log->severity === 'critical',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $log->severity === 'warning',
                                'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ! in_array($log->severity, ['critical', 'warning']),
                            ])>{{ ucfirst($log->severity) }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $log->code ?? '—' }}</td>
                        <td class="max-w-md truncate px-4 py-2">{{ $log->message }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-slate-500 dark:text-slate-400">{{ $log->created_at->format('H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">No errors for this day. All quiet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
