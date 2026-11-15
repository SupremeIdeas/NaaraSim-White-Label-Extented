<div class="mx-auto max-w-4xl" wire:poll.4s.keep-alive data-system-health data-health-state="{{ $verdict['state'] }}">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]"><svg class="h-7 w-7" aria-hidden="true"><use href="#i-sh-pulse"></use></svg></span>
            <div>
                <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">System health</h1>
                <p class="text-sm text-[rgb(var(--nx-text-2))]">Is the live cron firing and the queue draining? It checks itself every few seconds and turns green the moment the cron runs.</p>
            </div>
        </div>
        <button type="button" wire:click="refreshHealth" wire:loading.attr="disabled" wire:target="refreshHealth" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm shrink-0">
            <x-icon name="refresh" class="h-4 w-4" /> Refresh
        </button>
    </div>

    {{-- Headline verdict: live (green), waiting for the first cron run (amber), attention (red). --}}
    @php
        $pulse = $verdict['pulse'];
        $tone = ['live' => 'ok', 'waiting' => 'warn', 'attention' => 'bad'][$verdict['state']];
        $glyph = ['live' => 'i-sh-ok', 'waiting' => 'i-sh-wait', 'attention' => 'i-sh-alert'][$verdict['state']];
    @endphp
    <div class="mb-5 rounded-3xl border-2 p-5 sm:p-6" style="border-color:rgb(var(--nx-{{ $tone }}));background:rgb(var(--nx-surface))" role="status" aria-live="polite" data-health-banner="{{ $verdict['state'] }}">
        <div class="flex items-start gap-4">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl" style="background:rgb(var(--nx-{{ $tone }}));color:rgb(var(--nx-surface))"><svg class="h-8 w-8" aria-hidden="true"><use href="#{{ $glyph }}"></use></svg></span>
            <div class="min-w-0 flex-1">
                @if ($verdict['state'] === 'live')
                    <p class="text-xl font-bold" style="color:rgb(var(--nx-ok))">All systems are running smoothly</p>
                    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Cron heartbeat {{ \App\Support\CronPulse::human($pulse['age']) }} ago. The queue is draining.@if (count($verdict['overdue']) === 0) No overdue tasks.@endif @if ($verdict['never_ran'] > 0)<span class="block text-xs">{{ $verdict['never_ran'] }} slower task{{ $verdict['never_ran'] === 1 ? '' : 's' }} (daily, weekly, monthly) will check in when their turn comes.</span>@endif</p>
                @elseif ($verdict['state'] === 'waiting')
                    <p class="text-xl font-bold" style="color:rgb(var(--nx-warn))">Waiting for your first cron run</p>
                    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">No scheduled task has reported in yet. Add the cron below (cPanel, a VPS crontab or a Cloudways cron job). This page keeps checking every few seconds and turns green by itself within a minute of the first run.</p>
                @else
                    <p class="text-xl font-bold" style="color:rgb(var(--nx-bad))">Attention required</p>
                    <ul class="mt-1 space-y-1 text-sm text-[rgb(var(--nx-text-2))]">
                        @foreach ($verdict['issues'] as $issue)
                            <li><b class="text-[rgb(var(--nx-text))]">{{ $issue['title'] }}.</b> {{ $issue['detail'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if ($verdict['state'] !== 'live')
                <a href="#health-details" class="ns-cta ns-cta--pill ns-cta--sm hidden shrink-0 sm:inline-flex">View details <x-icon name="chevron-right" class="h-4 w-4" /></a>
            @endif
        </div>
        @if ($verdict['state'] !== 'live' && collect($verdict['issues'])->contains(fn ($i) => in_array($i['key'], ['cron_stopped', 'overdue'], true)) || $verdict['state'] === 'waiting')
            <div class="mt-4 flex items-center gap-2 rounded-2xl bg-[rgb(var(--nx-surface-3))] px-3 py-2.5" x-data="{ done: false }">
                <svg class="h-5 w-5 shrink-0 text-[rgb(var(--nx-teal-ink))]" aria-hidden="true"><use href="#i-sh-term"></use></svg>
                <code class="min-w-0 flex-1 break-all text-xs text-[rgb(var(--nx-text))]" data-cron-line>{{ $cronLine }}</code>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm shrink-0" @click="navigator.clipboard.writeText(@js($cronLine)).then(() => { done = true; setTimeout(() => done = false, 1800) })"><x-icon name="copy" class="h-4 w-4" /> <span x-text="done ? 'Copied' : 'Copy'"></span></button>
            </div>
        @endif
        @if ($verdict['state'] === 'live')
            <p class="mt-3 inline-flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]"><span class="relative inline-flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" style="background:rgb(var(--nx-ok))"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full" style="background:rgb(var(--nx-ok))"></span></span> Live: checked just now</p>
        @endif
    </div>

    {{-- Queue --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3" id="health-details">
        <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]"><svg class="h-7 w-7" aria-hidden="true"><use href="#i-sh-db"></use></svg></span>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Queue connection</p>
                    <p class="text-lg font-bold" style="color:rgb(var(--nx-{{ $queueIsSync ? 'bad' : 'text' }}))">{{ $queueConnection }}</p>
                    <p class="flex items-center gap-1.5 text-xs text-[rgb(var(--nx-text-2))]"><span class="h-2 w-2 rounded-full" style="background:rgb(var(--nx-{{ $queueIsSync ? 'bad' : 'ok' }}))"></span>{{ $queueIsSync ? 'Should be database or redis' : 'Live' }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]"><svg class="h-7 w-7" aria-hidden="true"><use href="#i-sh-stack"></use></svg></span>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Queue backlog</p>
                    <p class="text-lg font-bold text-[rgb(var(--nx-text))]">{{ is_null($queueBacklog) ? '—' : number_format($queueBacklog) }}</p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ is_null($queueBacklog) ? 'Not a database queue' : 'pending jobs' }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 col-span-2 sm:col-span-1">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]"><svg class="h-7 w-7" aria-hidden="true"><use href="#i-sh-timer"></use></svg></span>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Oldest job</p>
                    <p class="text-lg font-bold text-[rgb(var(--nx-text))]">{{ is_null($oldestJobAge) ? '—' : \Illuminate\Support\Carbon::now()->subSeconds($oldestJobAge)->diffForHumans(null, true) }}</p>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ is_null($oldestJobAge) ? 'queue empty' : 'waiting to drain' }}</p>
                </div>
            </div>
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
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Worker driver</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ $workerDriver }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
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
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Pending jobs</p>
            <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ number_format(collect($pendingByQueue)->sum('length')) }}</p>
            <p class="mt-0.5 text-[11px] text-slate-400">across {{ count($pendingByQueue) ?: 0 }} queue{{ count($pendingByQueue) === 1 ? '' : 's' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Failed jobs</p>
            <p @class(['mt-1 text-lg font-bold', 'text-red-600 dark:text-red-400' => $failedCount > 0, 'text-slate-900 dark:text-slate-100' => $failedCount === 0])>{{ number_format($failedCount) }}</p>
            @if (! is_null($redisReachable))
                <p class="mt-0.5 text-[11px] {{ $redisReachable ? 'text-slate-400' : 'text-red-500' }}">Redis {{ $redisReachable ? 'reachable' : 'unreachable' }}</p>
            @endif
        </div>
    </div>

    {{-- Per-queue breakdown (Horizon workload when available) --}}
    @if (count($pendingByQueue))
        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2A2E34] dark:bg-[#17191C]">
            <div class="border-b border-slate-100 p-4 dark:border-[#373a3f]">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Queues</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-[#373a3f]">
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
        <div class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2A2E34] dark:bg-[#17191C]">
            <div class="border-b border-slate-100 p-4 dark:border-[#373a3f]">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent failed jobs</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-[#373a3f]">
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
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">NCI learning health</h2>
            <span class="text-[11px] uppercase tracking-wide text-slate-400">Layer 3</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-100 p-3 dark:border-[#373a3f]">
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
            <div class="rounded-xl border border-slate-100 p-3 dark:border-[#373a3f]">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Nightly recompute</p>
                <p @class(['mt-1 text-lg font-bold', 'text-red-600 dark:text-red-400' => $nci['recompute_overdue'], 'text-slate-900 dark:text-slate-100' => ! $nci['recompute_overdue']])>
                    {{ $nci['recompute_ago'] ?? 'Not run yet' }}
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
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
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
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60 dark:border-[#2A2E34] dark:text-slate-300 dark:hover:bg-[#373a3f]">
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
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2A2E34] dark:bg-[#17191C]">
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
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white dark:border-[#2A2E34] dark:bg-[#17191C]"
         x-data="{ tab: @js($hostingMode) }">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 dark:border-[#373a3f]">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Hosting &amp; background setup</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Detected: <span class="font-semibold text-primary dark:text-teal-300">{{ $hostingMode === 'vps' ? 'VPS (Redis + Horizon)' : 'Shared hosting (cron-driven queue)' }}</span>.
                    Follow the steps for your host, in order.
                </p>
            </div>
            <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 dark:bg-white/5">
                <button type="button" @click="tab = 'shared'" :class="tab === 'shared' ? 'bg-white text-primary shadow-sm dark:bg-[#373a3f] dark:text-teal-300' : 'text-slate-400'" class="rounded-md px-3 py-1 text-xs font-semibold transition">Shared / cPanel</button>
                <button type="button" @click="tab = 'vps'" :class="tab === 'vps' ? 'bg-white text-primary shadow-sm dark:bg-[#373a3f] dark:text-teal-300' : 'text-slate-400'" class="rounded-md px-3 py-1 text-xs font-semibold transition">VPS / Cloudways</button>
            </div>
        </div>

        @foreach (['shared', 'vps'] as $mode)
            <div x-show="tab === '{{ $mode }}'" x-cloak class="space-y-3 p-4">
                @foreach ($hostingSteps[$mode] as $step)
                    <div class="rounded-xl border border-slate-100 p-3 dark:border-[#373a3f]">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $step[0] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $step[1] }}</p>
                        @if (! empty($step[2]))
                            <div class="mt-2" x-data="{ copied: false }">
                                <div class="flex items-start gap-2">
                                    <pre class="min-w-0 flex-1 overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-[11px] leading-relaxed text-slate-100 dark:bg-black/40"><code>{{ $step[2] }}</code></pre>
                                    <button type="button" @click="navigator.clipboard.writeText(@js($step[2])); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="shrink-0 rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-[#2A2E34] dark:hover:bg-[#373a3f]" aria-label="Copy">
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
    <div class="rounded-2xl border border-slate-200 bg-white dark:border-[#2A2E34] dark:bg-[#17191C]">
        <div class="border-b border-slate-100 p-4 dark:border-[#373a3f]">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Scheduled tasks</h2>
            <p class="text-xs text-slate-400">Each task's last successful run + its most recent attempt's outcome. "Never" or "overdue" means the cron isn't firing.</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-[#373a3f]">
            @foreach ($tasks as $task)
                @php
                    $state = $task['outcome'] === 'skipped' ? 'skipped' : ($task['outcome'] === 'failed' || ($task['overdue'] && $task['last_run'] !== null) ? 'broken' : ($task['last_run'] === null ? 'waiting' : 'ok'));
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
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $task['ago'] ?? 'not run yet' }}</p>
                            @if ($task['last_run'])
                                <p class="text-[11px] text-slate-400">{{ $task['last_run'] }}</p>
                            @endif
                        </div>
                        <span class="ns-pill" data-task-state="{{ $state }}">{{ match ($state) { 'broken' => $task['overdue'] ? 'Overdue' : 'Failing', 'skipped' => 'Not configured', 'waiting' => 'Waiting for its turn', default => 'OK' } }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Live activity feed (Tier 4 #10 Phase B2.1): the most recent real
         executions across every scheduled job, newest first — genuine proof
         the platform is doing background work, not a static snapshot. This
         panel (and the whole page, via wire:poll above) refreshes on its own. --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white dark:border-[#2A2E34] dark:bg-[#17191C]">
        <div class="border-b border-slate-100 p-4 dark:border-[#373a3f]">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent activity</h2>
            <p class="text-xs text-slate-400">The last {{ $recentHeartbeats->count() }} scheduled-job runs, newest first — live proof-of-work, not a snapshot.</p>
        </div>
        @if ($recentHeartbeats->isEmpty())
            <p class="p-5 text-sm text-slate-500 dark:text-slate-400">No job has recorded a heartbeat yet — one will appear here the moment the cron next fires.</p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-[#373a3f]">
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
            <p class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-[#2A2E34] dark:text-slate-400">No webhook deliveries recorded yet. If a provider (e.g. Paystack) should be calling and nothing shows here, the webhook URL likely isn't registered on the provider's dashboard.</p>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-[#2A2E34]">
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
