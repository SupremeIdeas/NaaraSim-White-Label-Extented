<?php

namespace App\Http\Controllers;

use App\Support\Installer;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Web installer wizard (blueprint Section 22.1), styled after the CodeCanyon
 * flow the operator referenced: Welcome → Server Requirements → Database Setup
 * (Environment + Database tabs) → Done. A default super_admin is seeded so the
 * operator can sign in immediately (shown on the Done screen). Guarded by
 * EnsureNotInstalled — it only runs until the lock file is written.
 */
class InstallController extends Controller
{
    public function welcome()
    {
        return view('install.welcome');
    }

    public function requirements()
    {
        return view('install.requirements', [
            'requirements' => Installer::requirements(),
            'pass' => Installer::requirementsPass(),
        ]);
    }

    public function setup()
    {
        return view('install.setup');
    }

    public function install(Request $request)
    {
        $data = $request->validate([
            'app_name' => 'required|string|max:60',
            'app_url' => ['required', 'string', 'regex:#^https?://[^\s]+[^/]$#'], // no trailing slash
            'hosting_type' => 'required|in:shared,vps',
            'db_connection' => 'required|in:mysql,sqlite',
            'db_host' => 'required_if:db_connection,mysql|nullable|string',
            'db_port' => 'required_if:db_connection,mysql|nullable|string',
            'db_database' => 'required|string',
            'db_username' => 'required_if:db_connection,mysql|nullable|string',
            'db_password' => 'nullable|string',
        ], [
            'app_url.regex' => 'Please do not enter “/” at the end of the URL. Example: https://naarasim.com',
        ]);

        // Driver profile by hosting type (blueprint Sections 20 & 22):
        //  - Shared cPanel has no Redis and no long-running workers, so cache,
        //    session and queue all live in the database; a single cron entry
        //    (schedule:run) drives the scheduler AND drains the queue.
        //  - VPS uses Redis for all three and runs Horizon as a daemon.
        $drivers = $data['hosting_type'] === 'vps'
            ? ['CACHE_STORE' => 'redis', 'SESSION_DRIVER' => 'redis', 'QUEUE_CONNECTION' => 'redis']
            : ['CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'database'];

        // 1) Write .env.
        $env = array_filter([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => $data['app_url'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => $data['db_connection'],
            'DB_HOST' => $data['db_host'] ?? null,
            'DB_PORT' => $data['db_port'] ?? null,
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'] ?? null,
            'DB_PASSWORD' => $data['db_password'] ?? null,
            'CACHE_STORE' => $drivers['CACHE_STORE'],
            'SESSION_DRIVER' => $drivers['SESSION_DRIVER'],
            'QUEUE_CONNECTION' => $drivers['QUEUE_CONNECTION'],
        ], fn ($v) => $v !== null);

        if (! config('app.key')) {
            $env['APP_KEY'] = Installer::generateAppKey();
        }
        Installer::writeEnv($env);

        // 2) Point the DB connection at the new database, then migrate + seed.
        if (! app()->runningUnitTests() && $data['db_connection'] === 'mysql') {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);
            DB::purge('mysql');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]); // roles, pricing, default admin

        // 3) Link the public storage disk (for logo/media uploads without Wasabi)
        //    and warm the caches — production only.
        Installer::markInstalled();
        if (! app()->runningUnitTests()) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable $e) {
                // symlink may be pre-created on some hosts — ignore.
            }
            foreach (['config:cache', 'route:cache', 'view:cache', 'icons:cache'] as $command) {
                Artisan::call($command);
            }
        }

        return view('install.done', [
            'email' => DefaultAdminSeeder::EMAIL,
            'password' => DefaultAdminSeeder::PASSWORD,
            'hosting' => $data['hosting_type'] === 'vps' ? 'vps' : 'shared',
        ]);
    }
}
