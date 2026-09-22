<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scheduler + queue health (HOTFIX §2). Turns the invisible "is the cron actually
 * running on the live host" question into something an admin can see at a glance.
 *
 * Each scheduled task's actual run is now recorded as a row in job_heartbeats
 * (Tier 4 #10 Phase B1 — see App\Support\JobHeartbeats), which is the single
 * source of truth for "did this run, and how did it go"; this class stays the
 * cadence registry (label + expected interval per task, mirrors
 * routes/console.php) and does the overdue math against that data — no
 * job's health is computed a second, different way anywhere else. It also
 * surfaces the live QUEUE_CONNECTION and the queue backlog, so a stopped cron or
 * an undrained queue — the confirmed root cause behind "payment didn't credit"
 * and "provider health widget is empty" — is obvious instead of re-diagnosed.
 */
class SchedulerHealth
{
    /**
     * The scheduled tasks we monitor: artisan command name => [label, expected
     * interval seconds]. Mirrors routes/console.php.
     *
     * @var array<string, array{0:string,1:int}>
     */
    public const TASKS = [
        'queue:work' => ['Queue drain (shared hosting)', 60],
        'providers:health-check' => ['Provider health check', 900],
        'ops:worker-health' => ['Worker watchdog (Redis/Horizon)', 300],
        'media:migrate-to-wasabi' => ['Media → Wasabi migration', 3600],
        'esim:sync' => ['eSIM catalogue sync', 86400],
        'virtual:renew' => ['Naara Line renewals', 86400],
        'backup:clean' => ['Backup cleanup', 86400],
        'backup:run' => ['Database backup', 86400],
        'merchants:auto-promote' => ['Merchant auto-promote', 86400],
        'payouts:rank' => ['Payout gateway ranking', 86400],
        'fx:sync' => ['FX rate sync', 86400],
        'partners:payout-run' => ['Partner payouts', 86400],
        'merchant:client-subscriptions' => ['Merchant client subscriptions', 86400],
        'giftcards:sync' => ['Naara Gift catalogue sync', 86400],
        'giftcards:reconcile-processing' => ['Naara Gift stuck-order reconcile', 900],
        'numbers:catalogue-sync' => ['Number catalogue sync', 604800],
        // NCI Layer 3 (BUILD-19 §7) — the learning recompute must keep running or
        // scores silently go stale; the weekly prune keeps provider_outcomes bounded.
        'nci:recompute' => ['NCI score recompute', 86400],
        'nci:prune-outcomes' => ['NCI outcome prune', 604800],
        // Connectivity Analytics (Part A) — mirrors the default of
        // esim.usage_sync_interval_minutes (15min); an admin-widened interval
        // just makes the overdue threshold slightly generous, never a false alarm.
        'esim:sync-usage' => ['eSIM usage snapshot sync', 900],
        'esim:prune-usage-snapshots' => ['eSIM usage snapshot prune', 604800],
        'payouts:earnings-run' => ['Merchant/referral earnings payouts', 86400],
        // Monthly close (1st of month) — a wide window so month-length variance
        // (28-31 days) never falsely flags it overdue between runs.
        'staff:compensation-close' => ['Staff compensation monthly close', 2678400],
        'brand-subscriptions:bill' => ['Brand Directory subscription billing', 86400],
        // Prompt 21-EXT2 §6 — completes elapsed white-label deploy timelines.
        'whitelabel:intake-deploy-check' => ['White-label deploy timeline check', 86400],
        // Erasure-fix Phase A stage 3 — permanently purges anonymized accounts
        // whose admin-configured retention window has elapsed.
        'account:purge-erased' => ['Account erasure retention purge', 86400],
    ];

    /** Record a task's successful completion. Accepts the raw scheduler command string. */
    public static function record(string $rawCommand): void
    {
        JobHeartbeats::finished($rawCommand);
    }

    /** Extract the artisan command name (first token after "artisan"). */
    public static function commandName(string $raw): ?string
    {
        if (preg_match('/artisan[\'"]?\s+([^\s\'"]+)/', $raw, $m)) {
            return $m[1];
        }
        // Already a bare command name / signature (e.g. from a test).
        $first = strtok(trim($raw), ' ');

        return $first !== false && $first !== '' ? $first : null;
    }

    /** The last SUCCESSFUL run of a task — what overdue math is measured against. */
    public static function lastRun(string $name): ?Carbon
    {
        return JobHeartbeats::lastSuccessAt($name);
    }

    /**
     * Per-task health report. `outcome`/`detail`/`duration_ms` reflect the most
     * recent ATTEMPT (any outcome), so a job that last succeeded two days ago
     * but just failed shows as failed, not as a stale "OK".
     *
     * @return list<array{name:string, label:string, expected:int, last_run:?string, ago:?string, overdue:bool, outcome:?string, detail:?string, duration_ms:?int}>
     */
    public static function report(): array
    {
        $rows = [];
        foreach (self::TASKS as $name => [$label, $expected]) {
            $last = self::lastRun($name);
            $latest = JobHeartbeats::latest($name);
            $threshold = $expected + max((int) ($expected * 0.33), 300);
            $overdue = $last === null || now()->diffInSeconds($last) > $threshold;

            $rows[] = [
                'name' => $name,
                'label' => $label,
                'expected' => $expected,
                'last_run' => $last?->toDateTimeString(),
                'ago' => $last?->diffForHumans(),
                'overdue' => $overdue,
                'outcome' => $latest?->outcome,
                'detail' => $latest?->detail,
                'duration_ms' => $latest?->duration_ms,
            ];
        }

        return $rows;
    }

    /** Any monitored task overdue → the cron is probably not firing. */
    public static function anyOverdue(): bool
    {
        foreach (self::report() as $row) {
            if ($row['overdue']) {
                return true;
            }
        }

        return false;
    }

    public static function queueConnection(): string
    {
        return (string) config('queue.default');
    }

    /** True when jobs run inline in the web request (dangerous in production). */
    public static function queueIsSync(): bool
    {
        return self::queueConnection() === 'sync';
    }

    /** Pending jobs in the database queue backlog (null when not a DB queue). */
    public static function queueBacklog(): ?int
    {
        if (self::queueConnection() !== 'database') {
            return null;
        }
        try {
            return (int) DB::table('jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Oldest pending job age in seconds (null when empty / not a DB queue). */
    public static function oldestJobAgeSeconds(): ?int
    {
        if (self::queueConnection() !== 'database') {
            return null;
        }
        try {
            $ts = DB::table('jobs')->min('created_at');
            if ($ts === null) {
                return null;
            }

            // The jobs table stores created_at as a UNIX timestamp integer.
            return max(0, now()->timestamp - (int) $ts);
        } catch (\Throwable) {
            return null;
        }
    }
}
