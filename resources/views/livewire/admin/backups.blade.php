<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Backups &amp; data</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Database backups are stored on <span class="font-medium">{{ $disk }}</span> and encrypted. Dump engine on this host: <span class="font-medium">{{ $engine }}</span>.
    </p>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif
    @if ($error)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="x" class="h-4 w-4" /> {{ $error }}
        </div>
    @endif

    {{-- Backups --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <x-icon name="package" class="h-4 w-4 text-primary" /> Database backups
            </h2>
            <button type="button" wire:click="backupNow" wire:loading.attr="disabled" wire:target="backupNow"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="backupNow" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4" /> Back up now</span>
                <span wire:loading wire:target="backupNow" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
            </button>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-[#243352]">
            @forelse ($backups as $b)
                <div wire:key="bk-{{ $b['name'] }}" class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $b['name'] }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ number_format($b['size'] / 1048576, 2) }} MB · {{ \Carbon\Carbon::createFromTimestamp($b['last_modified'])->diffForHumans() }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button type="button" wire:click="download('{{ $b['path'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                            <x-icon name="download" class="h-3.5 w-3.5" /> Download
                        </button>
                        <button type="button" wire:click="restore('{{ $b['path'] }}')"
                                wire:confirm="Restore the database from {{ $b['name'] }}? The app will go into maintenance mode and a safety snapshot is taken first. This overwrites current data."
                                class="inline-flex items-center gap-1 rounded-lg border border-amber-300 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-900/60 dark:text-amber-400 dark:hover:bg-amber-950/40">
                            <x-icon name="refresh" class="h-3.5 w-3.5" /> Restore
                        </button>
                        <button type="button" wire:click="deleteBackup('{{ $b['path'] }}')" wire:confirm="Delete {{ $b['name'] }}?"
                                class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                            <x-icon name="trash" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">No backups yet. Run one above, or the scheduler makes them daily.</p>
            @endforelse
        </div>
    </section>

    {{-- Dataset export / import --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="file-text" class="h-4 w-4 text-primary" /> Portable datasets
        </h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Move reference data between environments. Import always previews changes (dry-run) before you commit, and commits inside a transaction.</p>

        {{-- Export --}}
        <div class="mb-6">
            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Export tables</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($exportable as $table)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 dark:border-[#2D4060] dark:text-slate-200">
                        <input type="checkbox" wire:model="exportTables" value="{{ $table }}" class="rounded text-primary"> {{ $table }}
                    </label>
                @endforeach
                <button type="button" wire:click="exportDataset" @disabled(empty($exportTables))
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                    <x-icon name="download" class="h-4 w-4" /> Export selected
                </button>
            </div>
        </div>

        {{-- Import --}}
        <div class="border-t border-slate-100 pt-4 dark:border-[#243352]">
            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Import a dataset (.json)</label>
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="datasetFile" accept="application/json,.json" class="text-sm text-slate-600 dark:text-slate-300">
                <button type="button" wire:click="analyzeImport" wire:loading.attr="disabled" wire:target="analyzeImport,datasetFile" @disabled(! $datasetFile)
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                    Preview changes
                </button>
            </div>

            @if ($dryRun !== null)
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-[#2D4060] dark:bg-[#243352]">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dry-run — nothing written yet</p>
                    <ul class="space-y-1 text-sm text-slate-700 dark:text-slate-200">
                        @foreach ($dryRun as $table => $counts)
                            <li><span class="font-medium">{{ $table }}</span>: {{ $counts['new'] }} new, {{ $counts['existing'] }} already present</li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="commitImport" wire:confirm="Commit this import?"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                        <x-icon name="check" class="h-4 w-4" /> Commit import
                    </button>
                </div>
            @endif
        </div>
    </section>
</div>
