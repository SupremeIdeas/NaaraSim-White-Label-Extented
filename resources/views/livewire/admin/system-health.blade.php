<div class="mx-auto max-w-4xl" wire:poll.10s>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">System health</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Is the live cron firing and the queue draining? If a task is overdue, fix the cPanel cron — not the code. This page refreshes itself every few seconds.</p>
        </div>
        <button type="button" wire:click="refreshHealth" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
            <x-icon name="refresh" class="mr-1 inline h-4 w-4" /> Refresh
        </button>
    </div>

    {{-- Headline verdict --}}
    @if ($anyOverdue || $queueIsSync)
        <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 p-5 dark:border-red-900/50 dark:bg-red-950/30">
            <p class="flex items-center gap-2 font-semibold text-red-700 dark:text-red-300">
                <x-icon name="info" class="h-5 w-5" /> Something needs attention
            </p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                @if ($anyOverdue)
                    A scheduled task is overdue — the live cron may not be running. Re-add or repair the cPanel cron:
                    <code class="rounded bg-red-100 px-1 dark:bg-red-900/40">{{ $cronLine }}</code>
                @endif
                @if ($queueIsSync) The queue is running synchronously in production. @endif
            </p>
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/30">
            <p class="flex items-center gap-2 font-semibold text-green-700 dark:text-green-300">
                <x-icon name="check" class="h-5 w-5" /> Scheduler + queue look healthy
            </p>
        </div>
    @endif

    {{-- Queue --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Queue connection</p>
            <p @class([
                'mt-1 text-lg font-bold',
                'text-red-600 dark:text-red-400' => $queueIsSync,
                'text-slate-900 dark:text-slate-100' => ! $queueIsSync,
            ])>{{ $queueConnection }}</p>
            @if ($queueIsSync)
                <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">Should be database or redis in production.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Queue backlog</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ is_null($queueBacklog) ? '—' : number_format($queueBacklog) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">{{ is_null($queueBacklog) ? 'Not a database queue' : 'pending jobs' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Oldest job</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ is_null($oldestJobAge) ? '—' : \Illuminate\Support\Carbon::now()->subSeconds($oldestJobAge)->diffForHumans(null, true) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">{{ is_null($oldestJobAge) ? 'queue empty' : 'waiting to drain' }}</p>
        </div>
    </div>

    {{-- ============ Workers & background jobs (Platform Health) ============
         The live Redis/Horizon picture. On a VPS this is where you confirm the
         workers are alive and draining; the queue-backlog card above only reads
         a database queue, so this is the VPS-visible view. --}}
    @if ($worker['healthy'])
        <div class="mb-3 flex items-center gap-2 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
            <x-icon name="check" class="h-4 w-4" /> Background workers are processing jobs.
        </div>
    @else
        <div class="mb-3 rounded-2xl border border-red-300 bg-red-50 px-4 py-3 dark:border-red-900/50 dark:bg-red-950/30">
            <p class="flex items-center gap-2 text-sm font-semibold text-red-700 dark:text-red-300">
                <x-icon name="info" class="h-4 w-4" /> Background workers are degraded
            </p>
            <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $worker['reason'] }} Admins are alerted automatically (email + push) while this persists.</p>
            @if ($workerDriver === 'redis')
                <p class="mt-1 text-xs text-red-700/90 dark:text-red-300/90">On a VPS, (re)start the worker:
                    <code class="rounded bg-red-100 px-1 dark:bg-red-900/40">{{ $workerCmd }}</code> (or restart the <code class="rounded bg-red-100 px-1 dark:bg-red-900/40">horizon</code> supervisor/systemd service).</p>
            @endif
        </div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Worker driver</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ $workerDriver }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Horizon</p>
            @if (! $horizonInstalled)
                <p class="mt-1 text-lg font-bold text-slate-400">Not installed</p>
            @elseif ($horizonActive === true)
                <p class="mt-1 text-lg font-bold text-green-600 dark:text-green-400">Running</p>
                <a href="/horizon" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline dark:text-teal-300">
                    Open Horizon <x-icon name="chevron-right" class="h-3 w-3" />
                </a>
            @elseif ($horizonActive === false)
                <p class="mt-1 text-lg font-bold text-red-600 dark:text-red-400">Stopped</p>
                <p class="mt-0.5 text-[11px] text-slate-400">no master supervisor</p>
            @else
                <p class="mt-1 text-lg font-bold text-slate-400">—</p>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Pending jobs</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ number_format(collect($pendingByQueue)->sum('length')) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">across {{ count($pendingByQueue) ?: 0 }} queue{{ count($pendingByQueue) === 1 ? '' : 's' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Failed jobs</p>
            <p @class(['mt-1 text-lg font-bold', 'text-red-600 dark:text-red-400' => $failedCount > 0, 'text-slate-900 dark:text-slate-100' => $failedCount === 0])>{{ number_format($failedCount) }}</p>
            @if (! is_null($redisReachable))
                <p class="mt-0.5 text-[11px] {{ $redisReachable ? 'text-slate-400' : 'text-red-500' }}">Redis {{ $redisReachable ? 'reachable' : 'unreachable' }}</p>
            @endif
        </div>
    </div>

    {{-- Per-queue breakdown (Horizon workload when available) --}}
    @if (count($pendingByQueue))
        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="border-b border-slate-100 p-4 dark:border-[#243352]">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Queues</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-[#243352]">
                @foreach ($pendingByQueue as $q)
                    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $q['queue'] }}</span>
                        <span class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span><span class="font-semibold text-slate-800 dark:text-slate-100">{{ number_format($q['length']) }}</span> pending</span>
                            @if (! is_null($q['wait']))<span>{{ $q['wait'] }}s wait</span>@endif
                            @if (! is_null($q['processes']))<span>{{ $q['processes'] }} worker{{ $q['processes'] === 1 ? '' : 's' }}</span>@endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent failures --}}
    @if (count($recentFailed))
        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="border-b border-slate-100 p-4 dark:border-[#243352]">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent failed jobs</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-[#243352]">
                @foreach ($recentFailed as $f)
                    <div class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $f['job'] }}</span>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ $f['failed_at'] ? \Illuminate\Support\Carbon::parse($f['failed_at'])->diffForHumans() : '' }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-red-600 dark:text-red-400" title="{{ $f['error'] }}">{{ $f['error'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============ NCI Layer-3 health (BUILD-19 §7) ============
         NCI learns entirely from queued listeners and the nightly recompute. If
         the listeners are dying or the recompute has stalled, scores silently go
         stale with nothing else to show it — surface both here. --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">NCI learning health</h2>
            <span class="text-[11px] uppercase tracking-wide text-slate-400">Layer 3</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-100 p-3 dark:border-[#243352]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Failed NCI listeners</p>
                <p @class(['mt-1 text-lg font-bold', 'text-red-600 dark:text-red-400' => $nci['failed'] > 0, 'text-slate-900 dark:text-slate-100' => $nci['failed'] === 0])>{{ number_format($nci['failed']) }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    @if ($nci['failed'] > 0)
                        Queued score updates are dying — provider scores may be stale.
                    @else
                        All queued score updates are processing.
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-slate-100 p-3 dark:border-[#243352]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Nightly recompute</p>
                <p @class(['mt-1 text-lg font-bold', 'text-red-600 dark:text-red-400' => $nci['recompute_overdue'], 'text-slate-900 dark:text-slate-100' => ! $nci['recompute_overdue']])>
                    {{ $nci['recompute_ago'] ?? 'Never run' }}
                </p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    @if ($nci['recompute_overdue'])
                        Overdue — <code>nci:recompute</code> is not running; check the cron.
                    @else
                        Last ran {{ $nci['recompute_last'] }}.
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- ============ Maintenance: cache flusher ============ --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Caches</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Flush stale cached data after a config or content change. Safe on both VPS and shared cPanel.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="flushCache('app')" wire:loading.attr="disabled" wire:target="flushCache"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="refresh" class="h-4 w-4" wire:loading.remove wire:target="flushCache" />
                    <x-ui.spinner class="h-4 w-4" wire:loading wire:target="flushCache" />
                    Clear app cache
                </button>
                <button type="button" wire:click="flushCache('all')" wire:loading.attr="disabled" wire:target="flushCache"
                        wire:confirm="Clear ALL caches (application, config, routes, views)? The next few requests will be a little slower while they rebuild."
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    Clear all caches
                </button>
            </div>
        </div>
    </div>

    {{-- ============ Database updates (no-terminal migration runner) ============
         Owner request (2026-09-08): shared-cPanel installs often have no
         SSH/terminal access at all, so a code update that ships new
         migrations had no way to actually apply them; a VPS/Cloudways box
         has SSH but this saves logging in for a routine update either way.
         This runs `php artisan migrate --force` from a click — nothing
         here is cPanel-specific. Every migration this platform ships is
         additive-only (never drops/truncates live data — see CLAUDE.md),
         but it still changes the live schema, so it's gated to super_admin
         only (one level above the cache flush above) and shown with an
         explicit list of what will run before the admin commits to it. --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <x-icon name="database" class="h-4 w-4 text-primary dark:text-teal-300" />
                    Database updates
                </h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    After uploading new code, apply any new database changes here — no terminal or SSH needed. Safe on both VPS and shared cPanel.
                </p>
            </div>
            @if (Auth::user()->hasRole('super_admin'))
                <button type="button" wire:click="runMigrations" wire:loading.attr="disabled" wire:target="runMigrations"
                        @if (count($pendingMigrations) > 0) wire:confirm="Run {{ count($pendingMigrations) }} pending migration(s) now? This updates the live database schema (additive only — no existing data is touched or removed)." @endif
                        @disabled(count($pendingMigrations) === 0)
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60">
                    <x-icon name="upload" class="h-4 w-4" wire:loading.remove wire:target="runMigrations" />
                    <x-ui.spinner class="h-4 w-4" wire:loading wire:target="runMigrations" />
                    {{ count($pendingMigrations) > 0 ? 'Run '.count($pendingMigrations).' pending migration(s)' : 'Up to date' }}
                </button>
            @endif
        </div>

        @if (count($pendingMigrations) > 0)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-950/20">
                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ count($pendingMigrations) }} migration(s) waiting to run:</p>
                <ul class="mt-1.5 space-y-0.5 font-mono text-[11px] text-amber-700 dark:text-amber-400">
                    @foreach ($pendingMigrations as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">No pending migrations — the database schema is already up to date.</p>
        @endif

        @if ($migrationOutput !== '')
            <pre class="mt-3 max-h-48 overflow-auto rounded-xl bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-200 dark:bg-black">{{ $migrationOutput }}</pre>
        @endif

        @unless (Auth::user()->hasRole('super_admin'))
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Only a super admin can run database updates.</p>
        @endunless
    </div>

    {{-- ============ Hosting & background setup (dual: VPS + shared) ============
         NaaraSim runs on both a VPS (Redis + Horizon) and shared cPanel (a
         database queue drained by a one-minute cron). This shows the correct,
         ordered setup for the environment actually detected, with the real app
         path + PHP binary already filled into the cron line. --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]"
         x-data="{ tab: @js($hostingMode) }">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 dark:border-[#243352]">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Hosting &amp; background setup</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Detected: <span class="font-semibold text-primary dark:text-teal-300">{{ $hostingMode === 'vps' ? 'VPS (Redis + Horizon)' : 'Shared hosting (cron-driven queue)' }}</span>.
                    Follow the steps for your host, in order.
                </p>
            </div>
            <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 dark:bg-white/5">
                <button type="button" @click="tab = 'shared'" :class="tab === 'shared' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Shared / cPanel</button>
                <button type="button" @click="tab = 'vps'" :class="tab === 'vps' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-md px-3 py-1 text-xs font-semibold transition">VPS / Cloudways</button>
            </div>
        </div>

        @foreach (['shared', 'vps'] as $mode)
            <div x-show="tab === '{{ $mode }}'" x-cloak class="space-y-3 p-4">
                @foreach ($hostingSteps[$mode] as $step)
                    <div class="rounded-xl border border-slate-100 p-3 dark:border-[#243352]">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $step[0] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $step[1] }}</p>
                        @if (! empty($step[2]))
                            <div class="mt-2" x-data="{ copied: false }">
                                <div class="flex items-start gap-2">
                                    <pre class="min-w-0 flex-1 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-[11px] leading-relaxed text-slate-100 dark:bg-black/40"><code>{{ $step[2] }}</code></pre>
                                    <button type="button" @click="navigator.clipboard.writeText(@js($step[2])); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="shrink-0 rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]" aria-label="Copy">
                                        <x-icon name="copy" class="h-4 w-4" x-show="! copied" />
                                        <x-icon name="check" class="h-4 w-4 text-green-500" x-show="copied" x-cloak />
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Scheduled tasks (Tier 4 #10 Phase B2): three real states, not two —
         green (healthy), amber (its last attempt was skipped — e.g. a
         provider with no API key configured, a legitimate neutral state,
         never shown as an alarming red), or red (overdue or genuinely
         failing). Each card's detail line is the actual heartbeat's proof of
         the most recent real work, not just a light. --}}
    <div class="rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="border-b border-slate-100 p-4 dark:border-[#243352]">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Scheduled tasks</h2>
            <p class="text-xs text-slate-400">Each task's last successful run + its most recent attempt's outcome. "Never" or "overdue" means the cron isn't firing.</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-[#243352]">
            @foreach ($tasks as $task)
                @php
                    $state = $task['outcome'] === 'skipped' ? 'skipped' : ($task['overdue'] || $task['outcome'] === 'failed' ? 'broken' : 'ok');
                @endphp
                <div class="flex items-center justify-between gap-3 p-4" wire:key="task-{{ $task['name'] }}">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $task['label'] }}</p>
                        <p class="font-mono text-[11px] text-slate-400">{{ $task['name'] }} · every {{ \Illuminate\Support\Carbon::now()->subSeconds($task['expected'])->diffForHumans(null, true) }}</p>
                        @if ($task['detail'])
                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $task['detail'] }}">{{ $task['detail'] }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-right">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $task['ago'] ?? 'never run' }}</p>
                            @if ($task['last_run'])
                                <p class="text-[11px] text-slate-400">{{ $task['last_run'] }}</p>
                            @endif
                        </div>
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $state === 'broken',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $state === 'skipped',
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $state === 'ok',
                        ])>{{ match ($state) { 'broken' => $task['overdue'] ? 'Overdue' : 'Failing', 'skipped' => 'Not configured', default => 'OK' } }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Live activity feed (Tier 4 #10 Phase B2.1): the most recent real
         executions across every scheduled job, newest first — genuine proof
         the platform is doing background work, not a static snapshot. This
         panel (and the whole page, via wire:poll above) refreshes on its own. --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="border-b border-slate-100 p-4 dark:border-[#243352]">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent activity</h2>
            <p class="text-xs text-slate-400">The last {{ $recentHeartbeats->count() }} scheduled-job runs, newest first — live proof-of-work, not a snapshot.</p>
        </div>
        @if ($recentHeartbeats->isEmpty())
            <p class="p-5 text-sm text-slate-500 dark:text-slate-400">No job has recorded a heartbeat yet — one will appear here the moment the cron next fires.</p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-[#243352]">
                @foreach ($recentHeartbeats as $h)
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5" wire:key="heartbeat-{{ $h->id }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $h->job_name }}</span>
                                <span @class([
                                    'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $h->outcome === 'success',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $h->outcome === 'skipped',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $h->outcome === 'failed',
                                ])>{{ $h->outcome }}</span>
                                @if ($h->duration_ms !== null)
                                    <span class="shrink-0 text-[11px] text-slate-400">{{ $h->duration_ms }}ms</span>
                                @endif
                            </div>
                            @if ($h->detail)
                                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $h->detail }}">{{ $h->detail }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-400">{{ $h->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Inbound webhook deliveries (readiness Domain 13/14): proof a provider is
         actually calling us, accepted (2xx) or rejected (401 signature fail). --}}
    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Recent webhook deliveries</h2>
        @if ($webhookDeliveries->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-[#2D4060] dark:text-slate-400">No webhook deliveries recorded yet. If a provider (e.g. Paystack) should be calling and nothing shows here, the webhook URL likely isn't registered on the provider's dashboard.</p>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-[#2D4060]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400 dark:bg-white/5">
                        <tr><th class="px-4 py-2">Provider</th><th class="px-4 py-2">Path</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">When</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($webhookDeliveries as $d)
                            <tr class="text-slate-700 dark:text-slate-300">
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-white">{{ $d->provider }}</td>
                                <td class="px-4 py-2 font-mono text-xs">/{{ $d->path }}</td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status_code < 400 ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">{{ $d->status_code }}</span></td>
                                <td class="px-4 py-2 text-slate-400">{{ \Illuminate\Support\Carbon::parse($d->created_at)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
