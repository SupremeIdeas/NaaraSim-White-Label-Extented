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
     * @return array{state: string, pulse: array, issues: list<array{key: string, title: string, detail: string}>, never_ran: int, overdue: list<string>}
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

        return ['state' => $state, 'pulse' => $pulse, 'issues' => $issues, 'never_ran' => $neverRan, 'overdue' => $overdue];
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
