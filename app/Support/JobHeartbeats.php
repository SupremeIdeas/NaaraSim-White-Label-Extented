<?php

namespace App\Support;

use App\Models\JobHeartbeat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tier 4 #10 Phase B1 — the real heartbeat log every scheduled command writes
 * to on start and completion: a genuine outcome (success/failed/skipped) and
 * a short human-readable detail per run, not just a bare "last ran at"
 * timestamp. This is the single source of truth both the System Health hero
 * (Phase B2) and SchedulerHealth's own overdue math read from — no job's
 * health is computed a second, different way anywhere else.
 *
 * Wired from four Illuminate\Console\Events listeners in AppServiceProvider
 * (Starting/Finished/Failed/Skipped), so every command already registered in
 * routes/console.php gets a row for free, with zero per-command changes
 * required. A command MAY additionally call note() from inside its own
 * handle() to attach a richer "proof of work" detail (see esim:sync) —
 * purely additive, never required.
 */
class JobHeartbeats
{
    private const START_KEY = 'job_heartbeat:start:';

    private const NOTE_KEY = 'job_heartbeat:note:';

    /** A scheduled command is about to run — remember when, for duration_ms. */
    public static function starting(string $rawCommand): void
    {
        $name = SchedulerHealth::commandName($rawCommand);
        if ($name === null) {
            return;
        }
        Cache::put(self::START_KEY.$name, microtime(true), now()->addHour());
    }

    /**
     * Attach a short human-readable "proof of work" note to the NEXT heartbeat
     * row written for this command name (e.g. "1 provider synced; 6 skipped —
     * not configured"). Consumed and cleared the moment that row is written.
     */
    public static function note(string $commandName, string $detail): void
    {
        Cache::put(self::NOTE_KEY.$commandName, mb_substr($detail, 0, 500), now()->addHour());
    }

    public static function finished(string $rawCommand): void
    {
        self::write($rawCommand, 'success');
    }

    public static function failed(string $rawCommand, ?\Throwable $e = null): void
    {
        self::write($rawCommand, 'failed', $e?->getMessage());
    }

    public static function skipped(string $rawCommand): void
    {
        self::write($rawCommand, 'skipped');
    }

    private static function write(string $rawCommand, string $outcome, ?string $errorDetail = null): void
    {
        $name = SchedulerHealth::commandName($rawCommand);
        if ($name === null) {
            return;
        }

        try {
            $startedAtRaw = Cache::pull(self::START_KEY.$name);
            $startedAt = $startedAtRaw ? Carbon::createFromTimestamp((float) $startedAtRaw) : null;
            $durationMs = $startedAtRaw ? (int) round((microtime(true) - (float) $startedAtRaw) * 1000) : null;

            $detail = $errorDetail !== null ? mb_substr($errorDetail, 0, 500) : Cache::pull(self::NOTE_KEY.$name);

            JobHeartbeat::create([
                'job_name' => $name,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'outcome' => $outcome,
                'detail' => $detail,
            ]);
        } catch (\Throwable $e) {
            // Observability must never break the scheduler itself.
            Log::debug('[job-heartbeats] failed to record heartbeat: '.$e->getMessage());
        }
    }

    /** The most recent heartbeat for one job (any outcome), or null if it never ran. */
    public static function latest(string $jobName): ?JobHeartbeat
    {
        try {
            return JobHeartbeat::where('job_name', $jobName)->latest('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /** The most recent SUCCESSFUL run's finish time — what overdue math is measured against. */
    public static function lastSuccessAt(string $jobName): ?Carbon
    {
        try {
            $row = JobHeartbeat::where('job_name', $jobName)->where('outcome', 'success')->latest('id')->first();

            return $row?->finished_at;
        } catch (\Throwable) {
            return null;
        }
    }

    /** The most recent N heartbeats across every job, newest first — the live feed. */
    public static function recent(int $limit = 20): LazyCollection|\Illuminate\Database\Eloquent\Collection
    {
        try {
            return JobHeartbeat::latest('id')->limit($limit)->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Trim old rows so the table never grows unbounded. */
    public static function prune(int $days = 30): int
    {
        return JobHeartbeat::where('created_at', '<', now()->subDays($days))->delete();
    }
}
