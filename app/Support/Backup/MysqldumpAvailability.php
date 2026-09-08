<?php

namespace App\Support\Backup;

/**
 * Detects whether the `mysqldump` binary is callable on this host (blueprint
 * Section 28). Shared cPanel plans routinely disable shell functions or block
 * mysqldump — in that case NaaraSim falls back to the pure-PHP
 * ifsnop/mysqldump-php dumper (see IfsnopMysqlDumper + BackupServiceProvider).
 */
class MysqldumpAvailability
{
    public static function available(): bool
    {
        // A host that disables shell_exec can't run mysqldump anyway.
        if (! function_exists('shell_exec') || self::functionDisabled('shell_exec')) {
            return false;
        }

        $binary = trim((string) config('database.connections.mysql.dump.dump_binary_path', ''));
        $command = $binary !== '' ? rtrim($binary, '/\\').'/mysqldump' : 'mysqldump';

        $resolved = @shell_exec('command -v '.escapeshellarg($command).' 2>/dev/null');

        return is_string($resolved) && trim($resolved) !== '';
    }

    private static function functionDisabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array($function, $disabled, true);
    }
}
