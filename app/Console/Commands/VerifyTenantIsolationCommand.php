<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Tenant-isolation self-check (NAARASIM-URGENT-TENANT-ISOLATION §4.1). A deployed
 * instance runs this against ITSELF to catch the local misconfigurations that let
 * two supposedly-independent installs (e.g. the master domain and a White Label
 * subdomain) silently share users, sessions, or cached settings:
 *
 *   - a missing / placeholder APP_KEY (shared or unset ⇒ mutually decryptable
 *     session cookies between installs) — HARD FAIL;
 *   - a SESSION_DOMAIN scoped to a leading-dot parent domain (e.g. `.rehav.online`)
 *     that both a subdomain and the root would match ⇒ shared session state;
 *   - a Redis cache store with no distinct CACHE_PREFIX ⇒ cached settings from one
 *     install readable by another sharing the same Redis.
 *
 * It also prints the DB connection + database NAME (never credentials) so an
 * operator can eyeball that two installs point at genuinely different databases.
 *
 * Scope note: this is a LOCAL self-check only. The definitive cross-instance
 * collision check (comparing this instance's APP_KEY hash / DB name against the
 * values the master recorded at license issuance) is a separate, networked step
 * that belongs with the license-consumer layer — see
 * docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md and docs/TENANT-ISOLATION.md.
 * Comparing the actual live .env files of two installs is a manual operator step
 * (this process cannot see another install), documented in the runbook.
 */
class VerifyTenantIsolationCommand extends Command
{
    protected $signature = 'tenant:verify-isolation {--strict : Treat warnings as failures (non-zero exit)}';

    protected $description = 'Self-check this install for the local misconfigurations that break tenant isolation between installs.';

    public function handle(): int
    {
        $this->info('Tenant isolation self-check');
        $this->line('---------------------------');

        $hardFail = false;
        $warned = false;

        // 1. APP_KEY — must be set and not an obvious placeholder.
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            $this->error('[FAIL] APP_KEY is empty. Generate a UNIQUE key (php artisan key:generate) — never share it between installs.');
            $hardFail = true;
        } elseif (in_array($appKey, ['base64:', 'SomeRandomString', 'base64:SomeRandomString'], true)) {
            $this->error('[FAIL] APP_KEY looks like a placeholder. Generate a real, unique key per install.');
            $hardFail = true;
        } else {
            $this->line('[ ok ] APP_KEY is set (this must be UNIQUE per install — never copied between master and a white-label).');
        }

        // 2. SESSION_DOMAIN — a leading-dot parent domain shares sessions across subdomains.
        $sessionDomain = config('session.domain');
        if (is_string($sessionDomain) && str_starts_with($sessionDomain, '.')) {
            $this->warn("[WARN] SESSION_DOMAIN is '{$sessionDomain}' — a leading-dot parent domain is matched by BOTH the root and every subdomain, so two installs on the same parent domain would share session cookies. Set it to this install's exact host, or leave it empty (Laravel then scopes to the exact host).");
            $warned = true;
        } else {
            $this->line('[ ok ] SESSION_DOMAIN is exact-host or empty (no cross-subdomain session sharing from this setting).');
        }

        // 3. Redis cache with no distinct prefix — cached settings leak across installs.
        $cacheStore = (string) config('cache.default');
        $cachePrefix = (string) config('cache.prefix');
        if ($cacheStore === 'redis') {
            if ($cachePrefix === '') {
                $this->warn('[WARN] Cache store is Redis but CACHE_PREFIX is empty. Two installs sharing one Redis could read each other\'s cached settings. Set a distinct CACHE_PREFIX per install (and/or a distinct REDIS_DB / REDIS_CACHE_DB).');
                $warned = true;
            } else {
                $this->line("[ ok ] Redis cache prefix is set ('{$cachePrefix}') — cached values are namespaced to this install.");
            }
        } else {
            $this->line("[ ok ] Cache store is '{$cacheStore}' (not shared Redis).");
        }

        // 4. Print the DB target (name only, never credentials) for the operator to eyeball.
        $conn = (string) config('database.default');
        $dbName = (string) config("database.connections.{$conn}.database");
        $dbHost = (string) config("database.connections.{$conn}.host");
        $this->line("[info] DB connection '{$conn}' → database '{$dbName}' on host '{$dbHost}'.");
        $this->line('       Confirm this database is DEDICATED to this install — never shared with another install.');

        $this->newLine();
        if ($hardFail) {
            $this->error('Tenant isolation self-check FAILED — fix the [FAIL] item(s) above before this install goes into production.');

            return self::FAILURE;
        }
        if ($warned) {
            $this->warn('Tenant isolation self-check passed with warnings — review the [WARN] item(s) above.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Tenant isolation self-check passed.');

        return self::SUCCESS;
    }
}
