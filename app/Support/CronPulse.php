<?php

namespace App\Support;

use App\Models\JobHeartbeat;
use Illuminate\Support\Carbon;

/**
 * The live cron + queue verdict behind Admin > System health. It answers ONE question fast: "is it working right now?"
 *
 * Why this exists: the old page went red when ANY of ~80 scheduled tasks had never run, so a freshly installed site stayed red for days (monthly and weekly
 * tasks have not had their turn yet) even with the cron working perfectly. The signal that matters is the cron's own pulse (`ops:cron-tick`, every minute, plus
 * every other heartbeat any task writes): the moment the cron fires once the page turns green, and it turns red only when something that WAS running has stopped.
 *
 *   live       green  the cron pulsed within the last 2.5 minutes, the queue is draining, nothing that used to run is overdue
 *   waiting    amber  no heartbeat has ever been recorded (fresh install, cron not added yet): the page keeps checking and flips by itself
 *   attention  red    the pulse stopped, the queue is sync / stuck / its workers are down, or a task that used to run is overdue
 */
class CronPulse
{
    public const JOB = 'ops:cron-tick';

    /** A pulse older than this means the cron is not firing (it should arrive every minute). */
    public const LIVE_WITHIN_SECONDS = 150;

    /** A database-queue job older than this while the cron is alive means nothing is draining the queue. */
    public const STUCK_AFTER_SECONDS = 300;

    private const ENV_KEY = 'ops.cron_env';

    /**
     * What the REAL cron looks like, written by the cron itself on every tick (only when it changed): which PHP binary and version ran it, from which
     * folder, as which user. This is the ground truth the page compares the website against, so a cron that points at an old copy of the app, or at a
     * different PHP than the site, is named instead of silently "working".
     *
     * @return array{php_binary: string, php_version: string, sapi: string, path: string, user: string}
     */
    public static function currentEnvironment(): array
    {
        return [
            'php_binary' => (string) PHP_BINARY,
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'sapi' => PHP_SAPI,
            'path' => realpath(base_path()) ?: base_path(),
            'user' => function_exists('get_current_user') ? (string) get_current_user() : '',
        ];
    }

    public static function recordEnvironment(): void
    {
        try {
            $now = self::currentEnvironment();
            $stored = json_decode((string) \App\Models\Setting::getValue(self::ENV_KEY, ''), true);
            if (! is_array($stored) || array_diff_assoc($now, $stored) !== []) {
                \App\Models\Setting::setValue(self::ENV_KEY, json_encode($now + ['seen_at' => now()->toDateTimeString()]), 'ops');
            }
        } catch (\Throwable) {
            // observability only
        }
    }

    /** @return ?array{php_binary: string, php_version: string, sapi: string, path: string, user: string, seen_at?: string} as the cron last reported it */
    public static function cronEnvironment(): ?array
    {
        try {
            $v = json_decode((string) \App\Models\Setting::getValue(self::ENV_KEY, ''), true);

            return is_array($v) && isset($v['path'], $v['php_version']) ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{state: string, last: ?Carbon, age: ?int} state: live | waiting | stopped */
    public static function pulse(): array
    {
        try {
            $last = JobHeartbeat::query()->max('finished_at');
        } catch (\Throwable) {
            $last = null;
        }
        if ($last === null) {
            return ['state' => 'waiting', 'last' => null, 'age' => null];
        }
        $last = Carbon::parse($last);
        $age = max(0, (int) $last->diffInSeconds(now(), true));

        return ['state' => $age <= self::LIVE_WITHIN_SECONDS ? 'live' : 'stopped', 'last' => $last, 'age' => $age];
    }

    /**
     * @return array{state: string, pulse: array, issues: list<array{key: string, title: string, detail: string}>, never_ran: int, overdue: list<string>, web: array, cron: ?array}
     */
    public static function verdict(): array
    {
        $pulse = self::pulse();
        $issues = [];

        if ($pulse['state'] === 'stopped') {
            $issues[] = ['key' => 'cron_stopped', 'title' => 'The cron stopped', 'detail' => 'Nothing has run for '.self::human($pulse['age']).'. Re-add or repair the cron below.'];
        }
        if (SchedulerHealth::queueIsSync()) {
            $issues[] = ['key' => 'queue_sync', 'title' => 'The queue runs inline', 'detail' => 'QUEUE_CONNECTION=sync makes every background job slow down the page. Use database or redis.'];
        }
        if (QueueHealth::driver() === 'redis') {
            $worker = QueueHealth::workerVerdict();
            if (! ($worker['healthy'] ?? true)) {
                $issues[] = ['key' => 'workers', 'title' => 'Queue workers are down', 'detail' => (string) ($worker['reason'] ?? 'No worker is processing jobs.')];
            }
        }
        // Compare the REAL cron with this website: a cron on an old copy of the app or on another PHP version is a silent, classic mistake.
        $cron = $pulse['state'] === 'live' ? self::cronEnvironment() : null;
        if ($cron !== null && ($seenAt = $cron['seen_at'] ?? null) !== null && Carbon::parse($seenAt)->diffInSeconds(now(), true) < 7 * 86400) {
            $web = self::currentEnvironment();
            if (realpath($cron['path']) !== false ? realpath($cron['path']) !== $web['path'] : $cron['path'] !== $web['path']) {
                $issues[] = ['key' => 'cron_wrong_path', 'title' => 'The cron runs a different copy of the app', 'detail' => 'It runs from '.$cron['path'].' but this site lives in '.$web['path'].'. Use the command below.'];
            }
            if ($cron['php_version'] !== $web['php_version']) {
                $issues[] = ['key' => 'cron_php_mismatch', 'title' => 'The cron uses a different PHP version', 'detail' => 'The cron runs PHP '.$cron['php_version'].' while the site runs PHP '.$web['php_version'].'. Point the cron at the same PHP: use the command below.'];
            }
        }

        $oldest = SchedulerHealth::oldestJobAgeSeconds();
        if ($pulse['state'] === 'live' && $oldest !== null && $oldest > self::STUCK_AFTER_SECONDS) {
            $issues[] = ['key' => 'queue_stuck', 'title' => 'The queue is not draining', 'detail' => 'The oldest job has waited '.self::human($oldest).' although the cron is alive. Check the queue worker line.'];
        }

        $overdue = [];
        $neverRan = 0;
        foreach (SchedulerHealth::report() as $row) {
            if ($row['last_run'] === null) {
                $neverRan++;   // its turn has not come yet (or its module is off): not an alarm
            } elseif ($row['overdue']) {
                $overdue[] = $row['label'];
            }
        }
        if ($overdue !== [] && $pulse['state'] !== 'stopped') {
            $issues[] = ['key' => 'overdue', 'title' => count($overdue).' task'.(count($overdue) === 1 ? '' : 's').' that used to run '.(count($overdue) === 1 ? 'is' : 'are').' overdue', 'detail' => implode(', ', array_slice($overdue, 0, 3)).(count($overdue) > 3 ? ' and '.(count($overdue) - 3).' more' : '').'.'];
        }

        $state = $issues !== [] ? 'attention' : ($pulse['state'] === 'waiting' ? 'waiting' : 'live');

        return ['state' => $state, 'pulse' => $pulse, 'issues' => $issues, 'never_ran' => $neverRan, 'overdue' => $overdue, 'web' => self::currentEnvironment(), 'cron' => self::cronEnvironment()];
    }

    public static function human(?int $seconds): string
    {
        if ($seconds === null) {
            return 'a while';
        }
        if ($seconds < 90) {
            return $seconds.' seconds';
        }
        if ($seconds < 5400) {
            return (int) round($seconds / 60).' minutes';
        }

        return (int) round($seconds / 3600).' hours';
    }
}
