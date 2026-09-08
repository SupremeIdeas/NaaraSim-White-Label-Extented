<?php

namespace App\Support;

/**
 * Detects migrations present in the codebase but not yet recorded in the
 * `migrations` table — the "what would `php artisan migrate` do?" preview
 * for the System Health migration runner (2026-09-08). Host-agnostic (no
 * cPanel-specific assumption anywhere in here) — used identically on a
 * shared-cPanel install with no terminal at all, and on a VPS/Cloudways
 * box where it just saves an SSH round trip for a routine update: an admin
 * needs to see, before clicking anything, exactly which files are about
 * to run.
 *
 * Reuses Laravel's own Migrator/repository resolution (not a filesystem-vs-
 * text-output diff) so the count can never drift from what
 * `php artisan migrate` would actually do.
 */
class PendingMigrations
{
    /** @return list<string> migration names (no .php extension), oldest first */
    public static function names(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();

            $pending = array_diff(array_keys($files), $ran);
            sort($pending);

            return array_values($pending);
        } catch (\Throwable) {
            // Repository table missing, DB unreachable, etc. — same
            // fail-safe posture as the rest of System Health's widgets.
            return [];
        }
    }

    public static function count(): int
    {
        return count(self::names());
    }
}
