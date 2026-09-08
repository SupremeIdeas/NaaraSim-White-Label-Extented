<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usage snapshot sync (Connectivity Analytics blueprint Part A §2.3)
    |--------------------------------------------------------------------------
    |
    | How often the scheduler polls active eSIMs for a fresh usage reading.
    | 15 minutes is frequent enough to feel live while staying well inside
    | every major eSIM aggregator's documented per-minute rate limits.
    */
    'usage_sync_interval_minutes' => (int) env('ESIM_USAGE_SYNC_INTERVAL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Snapshot retention (blueprint §2.7)
    |--------------------------------------------------------------------------
    |
    | Raw snapshots accumulate fast (every active eSIM x every sync interval).
    | Rows older than this are pruned weekly by esim:prune-usage-snapshots.
    */
    'usage_snapshot_retention_days' => (int) env('ESIM_USAGE_SNAPSHOT_RETENTION_DAYS', 90),

];
