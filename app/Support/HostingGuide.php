<?php

namespace App\Support;

/**
 * Dual-hosting setup guidance for the admin System Health page. NaaraSim ships
 * to run on BOTH a VPS (Cloudways-style: Redis + Horizon persistent workers) and
 * shared hosting (Namecheap/cPanel: a database queue drained by a one-minute
 * cron). This turns "how do I wire the background layer on THIS host, in the
 * right order?" into copy-paste steps, keyed to the environment actually
 * detected, with the concrete app path + PHP binary filled in.
 */
class HostingGuide
{
    /** 'vps' when the queue is Redis (Horizon), else 'shared' (database cron drain). */
    public static function mode(): string
    {
        return QueueHealth::driver() === 'redis' ? 'vps' : 'shared';
    }

    // Installer is the single authority for the binary/path/cron strings (it runs
    // at install time, when correctness matters most). HostingGuide delegates to
    // it for every piece of shared logic so the two can never drift again — the
    // fpm/apache-safe phpBinary fallback in particular used to live only in
    // Installer, so System Health could print a broken cron line.

    /** Absolute app path for the cron line (real on the host, copy-paste ready). */
    public static function appPath(): string
    {
        return Installer::appPath();
    }

    /** The PHP binary for cron (fpm/apache-safe fallback lives in Installer). */
    public static function phpBinary(): string
    {
        return Installer::phpBinary();
    }

    /** The one scheduler cron both hosting modes need, minute-by-minute. */
    public static function cronLine(): string
    {
        return Installer::cronLine();
    }

    /** VPS-only: the persistent Horizon worker command (single-authority). */
    public static function queueWorkerCommand(): string
    {
        return Installer::queueWorkerCommand();
    }

    /**
     * Ordered setup steps per hosting mode. Each step is [title, detail, code?].
     *
     * @return array{shared: list<array{0:string,1:string,2?:string}>, vps: list<array{0:string,1:string,2?:string}>}
     */
    public static function steps(): array
    {
        $cron = self::cronLine();
        $php = self::phpBinary();
        $appPath = self::appPath();
        $worker = self::queueWorkerCommand();

        // A real, copy-paste Supervisor program — the difference between "keeps
        // Horizon alive across a crash/reboot" and a hint nobody can act on.
        $supervisor = "[program:naarasim-horizon]\n"
            ."process_name=%(program_name)s\n"
            ."command=$worker\n"
            ."directory=$appPath\n"
            ."autostart=true\n"
            ."autorestart=true\n"
            ."stopwaitsecs=3600\n"
            ."user=www-data\n"
            ."redirect_stderr=true\n"
            ."stdout_logfile=$appPath/storage/logs/horizon.log";

        return [
            'shared' => [
                ['1. Set the drivers in .env',
                    'Shared hosting has no Redis and no long-running worker, so use the database for the queue.',
                    "QUEUE_CONNECTION=database\nCACHE_STORE=database\nSESSION_DRIVER=database"],
                ['2. Migrate',
                    'Creates the jobs / failed_jobs / sessions tables the drivers above need.',
                    "$php artisan migrate --force"],
                ['3. Add ONE cron job (cPanel → Cron Jobs), every minute',
                    'This single cron runs the scheduler, which itself drains the queue every minute (queue:work --stop-when-empty). No separate worker to keep alive.',
                    $cron],
                ['4. Verify',
                    'Within ~1 minute the "Scheduled tasks" list below should show recent runs and the queue backlog should drain. If tasks stay overdue, the cron is not firing — re-check the cron path + PHP binary.',
                    null],
            ],
            'vps' => [
                ['1. Set the drivers in .env',
                    'A VPS runs Redis + Horizon, so point the queue (and cache/session) at Redis. Leave REDIS_PASSWORD=null ONLY if your Redis has no auth (Cloudways\' default local instance is unauthenticated). If you enabled a Redis password, put it here instead of null.',
                    "QUEUE_CONNECTION=redis\nCACHE_STORE=redis\nSESSION_DRIVER=redis\nREDIS_HOST=127.0.0.1\nREDIS_PORT=6379\nREDIS_PASSWORD=null"],
                ['2. Migrate',
                    'Still needed for failed_jobs + app tables.',
                    "$php artisan migrate --force"],
                ['3. Add the scheduler cron (Cloudways → Cron Job Management), every minute',
                    'Same one-minute scheduler cron as shared hosting — it runs the daily/periodic tasks. On Redis it does NOT drain the queue (Horizon does that), so it stays light.',
                    $cron],
                ['4. Keep Horizon alive with Supervisor',
                    'Horizon must run as a persistent process. On Cloudways add this under Application Settings → Supervisor; on a raw VPS save it to /etc/supervisor/conf.d/naarasim-horizon.conf, then: supervisorctl reread && supervisorctl update && supervisorctl start naarasim-horizon. autorestart=true brings it back after a crash or reboot — the difference between "processing" and a healthy Redis with no worker (the VPS-side equivalent of a dead cron).',
                    $supervisor],
                ['5. Reload workers on every deploy',
                    'Long-running workers hold old code in memory until restarted, so a release with new job code silently runs the OLD code until the worker recycles. Add this as the LAST line of your deploy script (Cloudways: Deployment via Git → Deployment hooks) so Supervisor restarts Horizon on fresh code.',
                    "$php $appPath/artisan horizon:terminate"],
                ['6. Verify',
                    'The "Workers & background jobs" panel above should show Horizon = Running with a live worker count. If it shows Stopped, the Supervisor process is not up — jobs will queue but never process, and admins get alerted automatically.',
                    null],
            ],
        ];
    }
}
