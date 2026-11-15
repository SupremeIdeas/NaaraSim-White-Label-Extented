<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Web installer core (blueprint Section 22.1). Drives the CodeCanyon-style
 * flow: requirements → DB → app → keys → finalize, guarded by an `installed`
 * lock file so /install is a one-time wizard. Provider keys left blank simply
 * show "Coming Soon" until filled (Section 17.4) — no code change to go live.
 */
class Installer
{
    /** Overridable in tests so we never clobber the real .env. */
    public static ?string $envPath = null;

    public static function envPath(): string
    {
        return self::$envPath ?? base_path('.env');
    }

    public static function lockPath(): string
    {
        return storage_path('installed');
    }

    public static function isInstalled(): bool
    {
        return file_exists(self::lockPath());
    }

    public static function markInstalled(): void
    {
        @file_put_contents(self::lockPath(), 'installed '.now()->toDateTimeString().PHP_EOL);
    }

    public static function unlock(): void
    {
        if (file_exists(self::lockPath())) {
            @unlink(self::lockPath());
        }
    }

    /**
     * Server requirements. Each row: label, ok, required.
     *
     * @return array<int, array{label: string, ok: bool, required: bool}>
     */
    public static function requirements(): array
    {
        $ext = fn (string $e) => extension_loaded($e);

        return [
            ['label' => 'PHP 8.2 or higher ('.PHP_VERSION.')', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'required' => true],
            ['label' => 'PDO extension', 'ok' => $ext('pdo'), 'required' => true],
            ['label' => 'mbstring extension', 'ok' => $ext('mbstring'), 'required' => true],
            ['label' => 'openssl extension', 'ok' => $ext('openssl'), 'required' => true],
            ['label' => 'curl extension', 'ok' => $ext('curl'), 'required' => true],
            ['label' => 'gd extension', 'ok' => $ext('gd'), 'required' => true],
            ['label' => 'zip extension', 'ok' => $ext('zip'), 'required' => true],
            ['label' => 'Redis extension (recommended)', 'ok' => $ext('redis'), 'required' => false],
            ['label' => 'storage/ is writable', 'ok' => is_writable(storage_path()), 'required' => true],
            ['label' => 'bootstrap/cache/ is writable', 'ok' => is_writable(base_path('bootstrap/cache')), 'required' => true],
        ];
    }

    public static function requirementsPass(): bool
    {
        foreach (self::requirements() as $req) {
            if ($req['required'] && ! $req['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge KEY=value pairs into the .env file (creating it from .env.example
     * when absent). Values containing spaces/# are quoted.
     *
     * @param  array<string, string>  $values
     */
    public static function writeEnv(array $values): void
    {
        $path = self::envPath();
        $contents = file_exists($path)
            ? file_get_contents($path)
            : (file_exists(base_path('.env.example')) ? file_get_contents(base_path('.env.example')) : '');

        foreach ($values as $key => $value) {
            $line = $key.'='.self::escape((string) $value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents);
    }

    private static function escape(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }

    public static function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Fresh-upload safeguard (must run before any encrypting middleware).
     *
     * The web installer renders through the `web` group (cookie + session
     * encryption), which needs an APP_KEY. A brand-new upload has no `.env` yet,
     * so without this the installer itself 500s with "No application encryption
     * key has been specified" before it can run. This seeds a key up front.
     *
     * It tries to persist a real random key to `.env` (so it survives the
     * GET-form → POST-submit round trip). If the project root isn't writable yet,
     * it falls back to a stable per-install key so sessions/CSRF stay consistent
     * across requests until setup completes and writes the real key. No-op once
     * installed, or once any key is already configured.
     */
    public static function bootstrapKey(): void
    {
        if (config('app.key') || self::isInstalled()) {
            return;
        }

        $persisted = false;
        $random = self::generateAppKey();
        try {
            self::writeEnv(['APP_KEY' => $random]); // creates .env from .env.example
            $persisted = true;
        } catch (\Throwable) {
            // Project root not writable yet — fall through to the stable key.
        }

        // A persisted key is read back from `.env` on later requests (this method
        // then no-ops); when we can't write, use a deterministic key so CSRF and
        // sessions don't break between the form load and its submission.
        $stable = 'base64:'.base64_encode(hash('sha256', base_path().'|naarasim-installer', true));
        config(['app.key' => $persisted ? $random : $stable]);
    }

    /**
     * Until the app is installed, force filesystem-backed session + cache and a
     * synchronous queue so the web installer never touches a database that does
     * not exist yet (the DB is created during setup). Without this, StartSession
     * tries to read a `sessions` table on a fresh upload and 500s with
     * "Connection refused". No-op once installed — the real drivers from `.env`
     * (database on shared, redis on VPS) take over.
     */
    public static function useSafeDriversUntilInstalled(): void
    {
        if (self::isInstalled()) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);
    }

    // ---- Cron / scheduler setup (blueprint Section 20) ----------------------

    /** True when this install runs the VPS profile (Redis queue + Horizon). */
    public static function isVps(): bool
    {
        return config('queue.default') !== 'database';
    }

    /**
     * Absolute application path for the cron / worker lines. THE single authority
     * for this value — HostingGuide and every Blade surface delegate here so the
     * path is computed in exactly one place.
     */
    public static function appPath(): string
    {
        return realpath(base_path()) ?: base_path();   // the real folder, never a symlinked or relative alias
    }

    /**
     * The PHP CLI binary the cron line should call, DETECTED from this server rather than guessed. THE single authority (HostingGuide and System health
     * delegate here).
     *
     * A web request often runs under php-fpm, php-cgi or lsphp, none of which is what a cron wants, so PHP_BINARY alone is not trusted. In order:
     *  1. the `php` that sits in this PHP's own bin directory (PHP_BINDIR: cPanel EasyApache `/opt/cpanel/ea-phpNN/root/usr/bin/php`, Debian `/usr/bin/php`),
     *  2. the `php` next to PHP_BINARY,
     *  3. a versioned CLI for the SAME major.minor the site runs on (`/usr/bin/php8.3`, `/usr/local/bin/php83`, `/usr/local/bin/ea-php83`),
     *  4. PHP_BINARY itself when it is a plain CLI or LiteSpeed (`lsphp`) binary that works from cron,
     *  5. `php`.
     * Every candidate must exist and be executable; a version mismatch is never silently accepted.
     */
    public static function phpBinary(): string
    {
        $mm = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $nodot = PHP_MAJOR_VERSION.PHP_MINOR_VERSION;
        $candidates = array_filter([
            defined('PHP_BINDIR') ? rtrim(PHP_BINDIR, '/').'/php' : null,
            PHP_BINARY ? dirname(PHP_BINARY).'/php' : null,
            "/usr/bin/php{$mm}", "/usr/local/bin/php{$mm}", "/usr/bin/php{$nodot}", "/usr/local/bin/php{$nodot}",
            "/usr/local/bin/ea-php{$nodot}", "/opt/cpanel/ea-php{$nodot}/root/usr/bin/php", "/opt/alt/php{$nodot}/usr/bin/php",
            "/usr/local/lsws/lsphp{$nodot}/bin/php",
        ]);
        foreach ($candidates as $c) {
            if (self::usableCli($c)) {
                return $c;
            }
        }
        $bin = (string) PHP_BINARY;
        if ($bin !== '' && ! preg_match('/fpm|apache|cgi|httpd/i', basename($bin))) {
            return $bin;   // a plain CLI or lsphp: it runs from cron as it runs the site
        }

        return 'php';
    }

    /** An executable that is not the fpm/cgi/apache SAPI. @internal */
    private static function usableCli(string $path): bool
    {
        try {
            return $path !== '' && @is_file($path) && @is_executable($path) && ! preg_match('/fpm|apache|cgi|httpd/i', basename($path));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * THE one cron entry the operator must add. This single line drives the whole
     * platform: the scheduler (health checks, catalogue sync, backups) AND — on
     * shared/cPanel — draining the queue every minute. Runs `schedule:run` each
     * minute, exactly as Laravel expects.
     */
    public static function cronLine(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: self::appPath();

        return '* * * * * cd '.$appPath.' && '.$php.' artisan schedule:run >> /dev/null 2>&1';
    }

    /** cPanel's cron UI splits schedule + command; give the command half too. */
    public static function cronCommandOnly(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: self::appPath();

        return 'cd '.$appPath.' && '.$php.' artisan schedule:run >> /dev/null 2>&1';
    }

    /** VPS-only: the long-running queue worker to run under a supervisor/Horizon. */
    public static function queueWorkerCommand(?string $php = null, ?string $appPath = null): string
    {
        $php = $php ?: self::phpBinary();
        $appPath = $appPath ?: self::appPath();

        return $php.' '.$appPath.'/artisan horizon';
    }

    /** Create the first super_admin (blueprint Section 22.1 step 3). */
    public static function createAdmin(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'referral_code' => Str::upper(Str::random(8)),
            'email_verified_at' => now(),
        ]);
        $user->setRole('super_admin');
        $user->assignRole('super_admin');

        return $user;
    }
}
