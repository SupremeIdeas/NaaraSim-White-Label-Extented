<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\EnvironmentGuard;
use App\Support\HostingGuide;
use App\Support\JobHeartbeats;
use App\Support\PendingMigrations;
use App\Support\QueueHealth;
use App\Support\SchedulerHealth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → System Health (HOTFIX §2). One place to answer "is the live cron
 * actually running and the queue draining?" — the confirmed root cause behind
 * "Paystack didn't credit" and "provider health widget is empty". Shows each
 * scheduled task's last run + overdue flag, the queue connection (loudly flagged
 * if sync in production), and the live queue backlog. Super-admin / admin only.
 */
#[Layout('components.layouts.admin')]
class SystemHealth extends Component
{
    /** Raw `php artisan migrate` output from the last run this page load —
     *  shown so an admin on a no-terminal install (shared cPanel, or a VPS
     *  they'd rather not SSH into for a routine update) can see exactly
     *  what happened, not just a toast. */
    public string $migrationOutput = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function refreshHealth(): void
    {
        // No-op: re-render pulls fresh values. Gives the admin a manual refresh.
        $this->dispatch('nx-toast', type: 'success', message: 'Refreshed.');
    }

    /**
     * Clear the platform's caches. `scope` picks how deep: 'app' clears just the
     * application cache store (safe, instant — the usual "flush cache" the owner
     * asked for); 'all' also clears the compiled config/route/view caches
     * (optimize:clear). Admin-only; safe to run on both VPS and cPanel.
     */
    public function flushCache(string $scope = 'app'): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        try {
            if ($scope === 'all') {
                Artisan::call('optimize:clear');
                $msg = 'All caches cleared (application, config, routes, views).';
            } else {
                Artisan::call('cache:clear');
                $msg = 'Application cache cleared.';
            }
            $this->dispatch('nx-toast', type: 'success', message: $msg);
        } catch (\Throwable $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Could not clear cache: '.$e->getMessage());
        }
    }

    /**
     * Runs `php artisan migrate --force` from a click — the no-terminal
     * update path (owner request, 2026-09-08). Nothing here is cPanel-
     * specific: it's plain Artisan::call() triggered by an HTTP request, so
     * it works identically on shared cPanel (no SSH available at all) and
     * on a VPS/Cloudways box (SSH available, but this saves logging in for
     * a routine update). Every migration this platform ships is
     * additive-only by discipline (CLAUDE.md: never overwrite live data),
     * but this still changes the live schema, so it's gated tighter than
     * the cache flush above (super_admin only) and audit-logged either way.
     */
    public function runMigrations(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $pending = PendingMigrations::names();
        if ($pending === []) {
            $this->dispatch('nx-toast', type: 'success', message: 'Already up to date — no pending migrations.');

            return;
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->migrationOutput = trim(Artisan::output());
            Auditor::log('admin.migrations_run', payload: ['migrations' => $pending]);
            $this->dispatch('nx-toast', type: 'success', message: count($pending).' migration(s) applied.');
        } catch (\Throwable $e) {
            Auditor::log('admin.migrations_failed', payload: ['error' => $e->getMessage(), 'migrations' => $pending]);
            $this->dispatch('nx-toast', type: 'error', message: 'Migration failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.system-health', [
            'pendingMigrations' => PendingMigrations::names(),
            'tasks' => SchedulerHealth::report(),
            'anyOverdue' => SchedulerHealth::anyOverdue(),
            'queueConnection' => SchedulerHealth::queueConnection(),
            'queueIsSync' => SchedulerHealth::queueIsSync(),
            'queueBacklog' => SchedulerHealth::queueBacklog(),
            'oldestJobAge' => SchedulerHealth::oldestJobAgeSeconds(),
            'envWarnings' => EnvironmentGuard::warnings(),
            // Worker layer (Platform Health): the live Redis/Horizon picture, so
            // "are background jobs actually processing?" is answerable on VPS too.
            'worker' => QueueHealth::workerVerdict(),
            'workerDriver' => QueueHealth::driver(),
            'horizonInstalled' => QueueHealth::horizonInstalled(),
            'horizonActive' => QueueHealth::horizonActive(),
            'redisReachable' => QueueHealth::redisReachable(),
            'pendingByQueue' => QueueHealth::pendingByQueue(),
            'failedCount' => QueueHealth::failedCount(),
            'recentFailed' => QueueHealth::recentFailed(),
            // NCI Layer-3 health (BUILD-19 §7): failed-listener count + the
            // recompute's last-run/overdue, so stale learning is visible.
            'nci' => $this->nciHealth(),
            // Dual-hosting setup guidance (VPS Horizon/Redis vs shared cPanel cron),
            // keyed to the detected environment, with the real cron line filled in.
            'hostingMode' => HostingGuide::mode(),
            'hostingSteps' => HostingGuide::steps(),
            'cronLine' => HostingGuide::cronLine(),
            'workerCmd' => HostingGuide::queueWorkerCommand(),
            // Recent inbound webhook deliveries (readiness Domain 13/14) — lets an
            // operator confirm a provider (Paystack, Twilio…) is actually calling.
            'webhookDeliveries' => $this->webhookDeliveries(),
            // Tier 4 #10 Phase B2: a genuinely live, timestamped feed of real
            // executions (not a snapshot) — every scheduled command's most
            // recent attempts, newest first.
            'recentHeartbeats' => JobHeartbeats::recent(20),
        ]);
    }

    /**
     * BUILD-19 §7 — NCI Layer-3 health at a glance: how many queued listeners
     * have died, and whether the daily recompute is running (or gone stale).
     *
     * Tier 4 #10 Phase B2.4 fix: this used to compute its OWN independent,
     * hardcoded "overdue after 26h" window for nci:recompute — a daily job —
     * which didn't match what SchedulerHealth::report() already computes for
     * that exact same task from its own TASKS registry (expected 86400s ->
     * threshold ~114912s, ~31.9h). Two different answers to "is this job
     * overdue" for one job is exactly the class of bug this phase exists to
     * catch, and directly violated the constraint that no job's health is
     * computed a second, different way. Now reads SchedulerHealth's own
     * report row for this task instead of recomputing anything.
     *
     * @return array{failed:int, recompute_last:?string, recompute_ago:?string, recompute_overdue:bool}
     */
    private function nciHealth(): array
    {
        $row = collect(SchedulerHealth::report())->firstWhere('name', 'nci:recompute');

        return [
            'failed' => QueueHealth::nciListenerFailedCount(),
            'recompute_last' => $row['last_run'] ?? null,
            'recompute_ago' => $row['ago'] ?? null,
            'recompute_overdue' => $row['overdue'] ?? true,
        ];
    }

    /** @return Collection<int, object> */
    private function webhookDeliveries()
    {
        try {
            return DB::table('webhook_deliveries')
                ->latest('created_at')->limit(15)->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
