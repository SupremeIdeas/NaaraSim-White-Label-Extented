<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Horizon;

/**
 * Worker + queue visibility for the admin System Health page. Unlike
 * SchedulerHealth (whose backlog only reads the `database` queue), this reports
 * the live Redis / Horizon picture — the setup a real VPS deployment runs — so
 * "are the background jobs actually being processed?" is answerable on VPS as
 * well as on shared cPanel. Everything is best-effort and wrapped so a probe can
 * never throw into the admin page.
 */
class QueueHealth
{
    /** The active queue driver (redis on a VPS, database/sync on shared hosting). */
    public static function driver(): string
    {
        return (string) config('queue.default');
    }

    /** Is Horizon installed in this build at all? */
    public static function horizonInstalled(): bool
    {
        return class_exists(Horizon::class);
    }

    /** Can we reach Redis right now? (null when Redis isn't the backend at all.) */
    public static function redisReachable(): ?bool
    {
        if (self::driver() !== 'redis' && (string) config('cache.default') !== 'redis'
            && (string) config('session.driver') !== 'redis') {
            return null;
        }
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Is a Horizon master supervisor actually running AND not paused? This is the
     * real "are the workers alive and draining" signal on a VPS. A master process
     * can exist while paused (php artisan horizon:pause, or an unresumed deploy) —
     * in that state Redis pings fine and a master is present, but nothing is
     * processed, which is exactly the silent-stall we must flag. So a master
     * counts as active only when its status is not 'paused'. null = not installed
     * / can't tell.
     */
    public static function horizonActive(): ?bool
    {
        if (! self::horizonInstalled()) {
            return null;
        }
        try {
            $masters = app(MasterSupervisorRepository::class)->all();
            if (count($masters) === 0) {
                return false;
            }

            // At least one master must be actually running (status !== 'paused').
            // An unknown/absent status is treated as running so we never false-alarm
            // on a Horizon build that doesn't report one.
            foreach ($masters as $master) {
                $status = is_object($master) ? ($master->status ?? null) : ($master['status'] ?? null);
                if ($status === null || $status !== 'paused') {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pending jobs per queue. Uses Horizon's workload repository when available
     * (richest — wait time + process count), else falls back to Queue::size().
     *
     * @return list<array{queue:string, length:int, wait:?int, processes:?int}>
     */
    public static function pendingByQueue(): array
    {
        // Horizon workload — per-queue length, wait seconds, live worker count.
        if (self::horizonInstalled()) {
            try {
                $rows = app(WorkloadRepository::class)->get();

                return collect($rows)->map(fn ($r) => [
                    'queue' => (string) ($r['name'] ?? 'default'),
                    'length' => (int) ($r['length'] ?? 0),
                    'wait' => isset($r['wait']) ? (int) $r['wait'] : null,
                    'processes' => isset($r['processes']) ? (int) $r['processes'] : null,
                ])->values()->all();
            } catch (\Throwable) {
                // fall through to the generic size probe
            }
        }

        try {
            return [[
                'queue' => 'default',
                'length' => (int) Queue::size('default'),
                'wait' => null,
                'processes' => null,
            ]];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Total pending jobs across every queue. */
    public static function pendingTotal(): int
    {
        return array_sum(array_map(fn ($q) => $q['length'], self::pendingByQueue()));
    }

    /** How many jobs have landed in failed_jobs (always a DB table). */
    public static function failedCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * BUILD-19 §7 — how many of the failed jobs are NCI Layer-3 listeners. NCI
     * learns entirely from queued listeners; if they are silently dying, scores
     * stop updating with nothing user-facing to show it. Surfacing the count on
     * System Health turns that invisible rot into a visible number. Matches the
     * listener namespace inside the serialized payload.
     */
    public static function nciListenerFailedCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')
                ->where('payload', 'like', '%App\\\\Services\\\\NCI\\\\Listeners%')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * The most recent failures, for the admin to eyeball what's breaking.
     *
     * @return list<array{connection:string, queue:string, job:string, error:string, failed_at:?string}>
     */
    public static function recentFailed(int $limit = 8): array
    {
        try {
            return DB::table('failed_jobs')->latest('id')->limit($limit)->get()
                ->map(function ($row) {
                    $payload = json_decode($row->payload ?? '{}', true);
                    $job = $payload['displayName'] ?? ($payload['job'] ?? 'job');
                    // First line of the exception is enough to triage.
                    $error = trim(strtok((string) ($row->exception ?? ''), "\n") ?: 'Unknown error');

                    return [
                        'connection' => (string) ($row->connection ?? ''),
                        'queue' => (string) ($row->queue ?? ''),
                        'job' => class_basename((string) $job),
                        'error' => Str::limit($error, 160),
                        'failed_at' => $row->failed_at ?? null,
                    ];
                })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The one-line "are workers healthy?" verdict used by both the UI and the
     * scheduled alert: degraded when the driver is redis but Horizon isn't
     * running, or Redis is unreachable.
     *
     * @return array{healthy:bool, reason:?string}
     */
    public static function workerVerdict(): array
    {
        if (self::driver() === 'sync') {
            return ['healthy' => false, 'reason' => 'Queue is running inline (sync) — jobs are not backgrounded.'];
        }
        if (self::redisReachable() === false) {
            return ['healthy' => false, 'reason' => 'Redis is not reachable.'];
        }
        if (self::driver() === 'redis' && self::horizonInstalled() && self::horizonActive() === false) {
            return ['healthy' => false, 'reason' => 'Horizon is not running (stopped or paused) — Redis jobs are queuing but nothing is processing them.'];
        }

        return ['healthy' => true, 'reason' => null];
    }
}
