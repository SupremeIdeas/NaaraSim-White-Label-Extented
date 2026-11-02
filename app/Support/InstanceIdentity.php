<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who owns this database. Every install gets a random INSTANCE_ID (in .env) and writes it to the `instance_identity` table of its own database. The
 * installer refuses a database that already holds another install's tables, and `whitelabel:doctor` / `tenant:verify-isolation` report a mismatch, so two
 * installs on one server can never quietly read and write each other's users, settings, themes or cron heartbeats. The cache, session and queue names are
 * derived from the same id, so a shared Redis (or a shared database cache table) cannot collide either. Nothing here ever reads or prints a credential.
 */
final class InstanceIdentity
{
    public const TABLE = 'instance_identity';

    public const MIGRATION = 'database/migrations/2027_05_01_100000_create_instance_identity_table.php';

    public static function id(): ?string
    {
        $id = config('app.instance_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** The per-install names for everything that could be shared on one server. */
    public static function envFor(string $id): array
    {
        return [
            'INSTANCE_ID' => $id,
            'CACHE_PREFIX' => 'wl_'.$id.'_cache_',
            'REDIS_PREFIX' => 'wl_'.$id.'_db_',
            'HORIZON_PREFIX' => 'wl_'.$id.'_horizon:',
            'SESSION_COOKIE' => 'wl_'.$id.'_session',
        ];
    }

    /** A short hash of host + port + database NAME (never the user or password), so two installs pointed at one database show the same value. */
    public static function fingerprint(): string
    {
        $c = config('database.connections.'.config('database.default'), []);

        return substr(hash('sha256', implode('|', [$c['driver'] ?? '', $c['host'] ?? '', $c['port'] ?? '', $c['database'] ?? ''])), 0, 16);
    }

    public static function edition(): string
    {
        return (string) config('app.edition', 'whitelabel');
    }

    /**
     * 'empty'       no tables: safe to install
     * 'ours'        the identity row carries THIS install's id (a re-run, or an upgrade)
     * 'foreign'     tables exist and they belong to someone else, or to no known install
     * 'unreachable' the database cannot be opened
     */
    public static function databaseState(?string $id = null): string
    {
        try {
            $tables = array_map(fn ($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t, Schema::getTables());
            $tables = array_values(array_filter($tables, fn ($n) => $n !== '' && ! str_starts_with($n, 'sqlite_')));
            if ($tables === []) {
                return 'empty';
            }
            $id ??= self::id();
            if ($id !== null && in_array(self::TABLE, $tables, true) && DB::table(self::TABLE)->where('instance_id', $id)->exists()) {
                return 'ours';
            }

            return 'foreign';
        } catch (\Throwable) {
            return 'unreachable';
        }
    }

    public static function row(): ?object
    {
        try {
            return Schema::hasTable(self::TABLE) ? DB::table(self::TABLE)->orderBy('id')->first() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Claim this database for this install. Never overwrites an existing owner. */
    public static function register(string $id): bool
    {
        if (! Schema::hasTable(self::TABLE) || DB::table(self::TABLE)->exists()) {
            return false;
        }
        DB::table(self::TABLE)->insert([
            'instance_id' => $id, 'edition' => self::edition(), 'app_key_hash' => hash('sha256', (string) config('app.key')),
            'db_fingerprint' => self::fingerprint(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return true;
    }

    /** null when everything matches, else a plain sentence for the doctor. */
    public static function mismatch(): ?string
    {
        $row = self::row();
        $id = self::id();
        if ($row === null) {
            return $id === null ? 'This install has no identity yet (run: php artisan whitelabel:doctor --adopt).' : 'INSTANCE_ID is set but this database has no identity row (run: php artisan whitelabel:doctor --adopt).';
        }
        if ($id === null) {
            return 'This database belongs to another install (INSTANCE_ID is not set in .env).';
        }
        if (! hash_equals((string) $row->instance_id, $id)) {
            return 'This database belongs to a DIFFERENT install (its identity does not match INSTANCE_ID in .env). Stop and use a separate database.';
        }
        if (! hash_equals((string) $row->app_key_hash, hash('sha256', (string) config('app.key')))) {
            return 'APP_KEY is not the key this database was installed with.';
        }

        return null;
    }
}
