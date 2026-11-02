<?php

namespace App\Console\Commands;

use App\Models\JobHeartbeat;
use App\Models\User;
use App\Support\InstanceIdentity;
use App\Support\Installer;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-minute health check of THIS install, safe to paste into a support ticket: it prints names and yes/no answers only (never a key, token, password or the
 * database user). Exit code 1 when something is broken that stops the site or mixes it up with another install.
 *   php artisan whitelabel:doctor            check
 *   php artisan whitelabel:doctor --adopt    give an install made before instance ids existed its own identity (never touches a database another install owns)
 */
class WhiteLabelDoctorCommand extends Command
{
    protected $signature = 'whitelabel:doctor {--adopt : Give this existing install an identity (INSTANCE_ID + per-install cache/session/queue names)}';

    protected $description = 'Check this install (PHP, .env, database ownership, migrations, admin, folders, drivers, scheduler, isolation).';

    private bool $failed = false;

    public function handle(): int
    {
        if ($this->option('adopt')) {
            return $this->adopt();
        }
        $this->info('White-label doctor');
        $this->line('------------------');

        $this->row(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION, 'PHP 8.2 or newer is required.');
        $missing = array_values(array_filter(['mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl'], fn ($e) => ! extension_loaded($e)));
        $this->row($missing === [], $missing === [] ? 'PHP extensions present' : 'Missing PHP extensions: '.implode(', ', $missing), 'Enable them in your PHP settings.');

        $this->row(file_exists(Installer::envPath()), '.env file present', 'Run the web installer or copy .env.example to .env.');
        $key = (string) config('app.key');
        $this->row(str_starts_with($key, 'base64:') && strlen(base64_decode(substr($key, 7))) === 32, 'APP_KEY is valid', 'Run: php artisan key:generate (only on a brand-new install: changing it logs everyone out).');
        $this->line('       APP_URL: '.config('app.url').'   ADMIN path: /'.\App\Support\AdminPath::current());

        foreach (['storage', 'bootstrap/cache'] as $dir) {
            $this->row(is_writable(base_path($dir)), $dir.' is writable', 'Give the web server write access to '.$dir.' (see set-permissions.sh).');
        }

        $dbOk = $this->database();
        if ($dbOk) {
            $this->migrations();
            $this->admin();
            $this->drivers();
            $this->scheduler();
        }
        $this->isolation();
        $this->licence();

        $this->newLine();
        $this->line($this->failed ? 'Result: PROBLEMS FOUND (see [FAIL] lines).' : 'Result: no blocking problems.');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function row(bool $ok, string $label, string $fix = '', bool $warnOnly = false): void
    {
        if ($ok) {
            $this->line('[ ok ] '.$label);

            return;
        }
        if ($warnOnly) {
            $this->warn('[WARN] '.$label.($fix ? ' — '.$fix : ''));

            return;
        }
        $this->failed = true;
        $this->error('[FAIL] '.$label.($fix ? ' — '.$fix : ''));
    }

    private function database(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->row(false, 'Database connection failed', 'Check DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env.');

            return false;
        }
        $c = config('database.connections.'.config('database.default'), []);
        $this->row(true, 'Database connects: '.($c['driver'] ?? '?').' "'.($c['database'] ?? '?').'" (fingerprint '.InstanceIdentity::fingerprint().')');

        $problem = InstanceIdentity::mismatch();
        $this->row($problem === null, $problem === null ? 'This database belongs to this install ('.InstanceIdentity::id().')' : $problem, '', false);

        return true;
    }

    private function migrations(): void
    {
        try {
            /** @var Migrator $m */
            $m = app('migrator');
            $m->getRepository()->setSource(config('database.default'));
            $ran = Schema::hasTable('migrations') ? $m->getRepository()->getRan() : [];
            $files = array_keys($m->getMigrationFiles([database_path('migrations')]));
            $pending = count(array_diff($files, $ran));
            $this->row($pending === 0, $pending === 0 ? 'All migrations have run' : $pending.' migration(s) pending', 'Run: php artisan migrate --force');
        } catch (\Throwable) {
            $this->row(false, 'Could not read migration status', 'Run: php artisan migrate --force');
        }
    }

    private function admin(): void
    {
        $has = Schema::hasTable('users') && User::query()->where('email', DefaultAdminSeeder::EMAIL)->exists();
        try {
            $any = Schema::hasTable('users') && User::query()->role('super_admin')->exists();
        } catch (\Throwable) {
            $any = false;   // the roles are not seeded yet
        }
        $this->row($any, $has ? 'Default admin exists' : ($any ? 'A super_admin exists (default admin was replaced)' : 'No super_admin exists'), 'Run: php artisan db:seed --class=DefaultAdminSeeder');
    }

    private function drivers(): void
    {
        $this->line('       cache='.config('cache.default').' session='.config('session.driver').' queue='.config('queue.default').' mail='.config('mail.default'));
        foreach (['cache' => config('cache.default') === 'database', 'sessions' => config('session.driver') === 'database', 'jobs' => config('queue.default') === 'database'] as $table => $needed) {
            if ($needed) {
                $this->row(Schema::hasTable($table), 'Table "'.$table.'" exists', 'Run: php artisan migrate --force');
            }
        }
        $this->row(! in_array(config('mail.default'), ['log', 'array'], true), 'Mail sends for real', 'MAIL_MAILER is "'.config('mail.default').'": no email leaves this install. Set SMTP in .env.', true);
    }

    private function scheduler(): void
    {
        try {
            $last = Schema::hasTable('job_heartbeats') ? JobHeartbeat::query()->max('started_at') : null;
        } catch (\Throwable) {
            $last = null;
        }
        $fresh = $last !== null && now()->diffInMinutes($last, true) <= 15;
        $this->row($fresh, $last ? 'Scheduler last ran '.now()->diffInMinutes($last, true).' minute(s) ago' : 'No scheduler run recorded yet', 'Add the cron line from the installer Done screen (php artisan schedule:run every minute).', true);
    }

    private function isolation(): void
    {
        $id = InstanceIdentity::id();
        $this->row($id !== null, $id !== null ? 'INSTANCE_ID is set' : 'INSTANCE_ID is not set (this install has no identity yet)', 'Run: php artisan whitelabel:doctor --adopt', true);
        $default = \Illuminate\Support\Str::slug((string) config('app.name'), '_').'_cache_';
        $own = config('cache.prefix') !== $default;
        $this->row($own, $own ? 'Cache prefix is this install\'s own' : 'Cache prefix is the shared default ('.$default.')', 'Two installs with that prefix share cached settings. Run: php artisan whitelabel:doctor --adopt', true);
        $cookie = (string) config('session.cookie');
        $own = ! in_array($cookie, ['naarasim_session', 'laravel_session'], true);
        $this->row($own, $own ? 'Session cookie name is this install\'s own' : 'Session cookie name is the shared default ('.$cookie.')', 'Run: php artisan whitelabel:doctor --adopt', true);
    }

    private function licence(): void
    {
        $has = (string) config('updater.api_token') !== '';
        $this->line('       Licence: '.($has ? 'activation token present' : 'not activated yet (Admin > White Label Activation)').'; platform URL '.((string) config('updater.original_platform_base_url') !== '' ? 'set' : 'not set'));
    }

    private function adopt(): int
    {
        $id = InstanceIdentity::id() ?: InstanceIdentity::newId();
        if (! Schema::hasTable(InstanceIdentity::TABLE)) {
            $this->call('migrate', ['--force' => true, '--path' => InstanceIdentity::MIGRATION]);
        }
        $row = InstanceIdentity::row();
        if ($row !== null && ! hash_equals((string) $row->instance_id, $id)) {
            $this->error('This database already belongs to another install ('.$row->instance_id.'). Nothing was changed. Use a separate database for this site.');

            return self::FAILURE;
        }
        if ($row === null) {
            InstanceIdentity::register($id);
        }
        $env = InstanceIdentity::envFor($id);
        $changed = [];
        foreach ($env as $k => $v) {
            if ($k === 'INSTANCE_ID' || (string) env($k) === '') {
                $changed[$k] = $v;   // never replaces a value the owner already chose
            }
        }
        Installer::writeEnv($changed);
        $this->info('Identity ready: '.$id);
        $this->line('Wrote '.implode(', ', array_keys($changed)).' to .env. Now run: php artisan config:clear && php artisan cache:clear');
        $this->warn('The session cookie name changed, so everyone is asked to sign in once more.');

        return self::SUCCESS;
    }
}
