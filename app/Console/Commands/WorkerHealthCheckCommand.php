<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Support\QueueHealth;
use Illuminate\Console\Command;

/**
 * Watches the background-worker layer and alerts admins when it stops
 * processing. On a VPS the queue is Redis + Horizon; if Horizon dies, jobs pile
 * up silently and money paths (orders, payouts, receipts) stall — the exact
 * "why didn't it process?" class this whole health surface exists to kill.
 *
 * The alert is dispatched SYNCHRONOUSLY (dispatchSync): the whole point is the
 * queue may be down, so a queued alert would never be delivered. This command is
 * driven by the schedule:run cron, which is independent of Horizon, so the inline
 * send (error-log + web push + email, throttled per code by AlertAdminJob) still
 * reaches admins. When workers are healthy it exits quietly.
 */
class WorkerHealthCheckCommand extends Command
{
    protected $signature = 'ops:worker-health';

    protected $description = 'Alert admins if the background-worker layer (Redis/Horizon) has stopped processing jobs.';

    public function handle(): int
    {
        $verdict = QueueHealth::workerVerdict();

        if ($verdict['healthy']) {
            $this->info('Workers healthy.');

            return self::SUCCESS;
        }

        $this->warn('Worker layer degraded: '.$verdict['reason']);

        // Synchronous on purpose — see the class docblock. Throttled per code
        // inside AlertAdminJob so a sustained outage alerts once, not every tick.
        AlertAdminJob::dispatchSync(
            code: 'worker-layer-degraded',
            message: 'Background workers are not processing jobs: '.$verdict['reason'],
            context: [
                'driver' => QueueHealth::driver(),
                'horizon_installed' => QueueHealth::horizonInstalled(),
                'horizon_active' => QueueHealth::horizonActive(),
                'redis_reachable' => QueueHealth::redisReachable(),
                'pending_total' => QueueHealth::pendingTotal(),
            ],
            severity: 'critical',
        );

        return self::SUCCESS;
    }
}
