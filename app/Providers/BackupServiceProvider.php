<?php

namespace App\Providers;

use App\Support\Backup\IfsnopMysqlDumper;
use App\Support\Backup\MysqldumpAvailability;
use App\Support\Backup\PhpSqliteDumper;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

/**
 * Wires the pure-PHP MySQL dump fallback (blueprint Section 28). When the
 * `mysqldump` binary isn't callable on this host — the classic shared-cPanel
 * situation — we register an ifsnop-based dumper for the `mysql` driver so
 * spatie/laravel-backup keeps working. On hosts where mysqldump IS available
 * (or on SQLite), nothing changes and the native dumper is used.
 */
class BackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! MysqldumpAvailability::available()) {
            DbDumperFactory::extend('mysql', fn () => new IfsnopMysqlDumper);
        }

        // SQLite backups never need the `sqlite3` binary — always dump via PDO.
        DbDumperFactory::extend('sqlite', fn () => new PhpSqliteDumper);
    }
}
