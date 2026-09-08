<?php

namespace App\Support\Backup;

use Ifsnop\Mysqldump\Mysqldump;
use Spatie\DbDumper\Databases\MySql;

/**
 * A pure-PHP MySQL dumper (blueprint Section 28) used when the `mysqldump`
 * binary is unavailable — typically shared cPanel hosting. It extends Spatie's
 * MySql dumper so spatie/laravel-backup configures it exactly like the native
 * one (host/db/user/password/port), but overrides the dump step to use
 * ifsnop/mysqldump-php instead of shelling out.
 *
 * Requires only SELECT and SHOW VIEW privileges on the database.
 */
class IfsnopMysqlDumper extends MySql
{
    public function dumpToFile(string $dumpFile): void
    {
        $host = $this->host !== '' ? $this->host : '127.0.0.1';
        $port = $this->port ?: 3306;

        $dsn = "mysql:host={$host};port={$port};dbname={$this->dbName}";
        if ($this->socket !== '') {
            $dsn = "mysql:unix_socket={$this->socket};dbname={$this->dbName}";
        }

        $dumper = new Mysqldump($dsn, $this->userName, $this->password, [
            'add-drop-table' => true,
            'single-transaction' => true,
            'skip-comments' => false,
            'no-autocommit' => true,
        ]);

        $dumper->start($dumpFile);
    }
}
