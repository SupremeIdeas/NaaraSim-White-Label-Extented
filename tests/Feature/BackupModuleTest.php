<?php

namespace Tests\Feature;

use App\Livewire\Admin\Backups;
use App\Jobs\RunBackupJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatasetService;
use App\Services\Backup\RestoreService;
use App\Support\Backup\IfsnopMysqlDumper;
use App\Support\Backup\MysqldumpAvailability;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BackupModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_the_php_fallback_dumper_is_used_for_mysql_when_registered(): void
    {
        // Simulate a host without mysqldump: register the ifsnop fallback…
        DbDumperFactory::extend('mysql', fn () => new IfsnopMysqlDumper);

        config(['database.connections.mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'database' => 'naara', 'username' => 'u', 'password' => 'p',
        ]]);

        $dumper = DbDumperFactory::createFromConnection('mysql');

        $this->assertInstanceOf(IfsnopMysqlDumper::class, $dumper);
        // …and it is a MySql dumper, so spatie configures it like the native one.
        $this->assertInstanceOf(MySql::class, $dumper);
        $this->assertSame('naara', $dumper->getDbName());
    }

    public function test_mysqldump_availability_is_detected_as_a_boolean(): void
    {
        $this->assertIsBool(MysqldumpAvailability::available());
    }

    public function test_sqlite_backups_use_the_binary_free_php_dumper(): void
    {
        // Registered globally in BackupServiceProvider — no sqlite3 binary needed.
        $this->assertInstanceOf(
            \App\Support\Backup\PhpSqliteDumper::class,
            DbDumperFactory::createFromConnection('sqlite')
        );

        // It produces a real, restorable .sql dump straight from PDO.
        $src = tempnam(sys_get_temp_dir(), 'src').'.sqlite';
        $pdo = new \PDO('sqlite:'.$src);
        $pdo->exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO widgets (name) VALUES ('alpha')");
        $pdo = null;

        $out = tempnam(sys_get_temp_dir(), 'dump').'.sql';
        (new \App\Support\Backup\PhpSqliteDumper)->setDbName($src)->dumpToFile($out);

        $sql = file_get_contents($out);
        @unlink($src);
        @unlink($out);

        $this->assertStringContainsString('CREATE TABLE widgets', $sql);
        $this->assertStringContainsString('INSERT INTO "widgets"', $sql);
        $this->assertStringContainsString('alpha', $sql);
    }

    public function test_backup_manager_lists_archives_on_the_disk(): void
    {
        Storage::fake('local');
        $manager = app(BackupManager::class);
        $dir = $manager->directory();

        Storage::disk('local')->put("{$dir}/2026-07-10-000000.zip", 'older');
        Storage::disk('local')->put("{$dir}/2026-07-13-000000.zip", 'newer');
        Storage::disk('local')->put("{$dir}/notes.txt", 'ignored'); // non-zip ignored

        $list = $manager->list();
        $this->assertCount(2, $list);
        $this->assertContainsOnly('array', $list);
        foreach ($list as $entry) {
            $this->assertStringEndsWith('.zip', $entry['name']);
        }
    }

    public function test_backup_now_dispatches_the_queued_job(): void
    {
        Queue::fake();

        Livewire::actingAs($this->withRole('super_admin'))->test(Backups::class)
            ->call('backupNow');

        Queue::assertPushed(RunBackupJob::class);
    }

    public function test_the_backups_page_is_super_admin_only(): void
    {
        $this->actingAs($this->withRole('admin'))->get('/adminmaster/backups')->assertForbidden();
        $this->actingAs($this->withRole('super_admin'))->get('/adminmaster/backups')->assertOk();
    }

    public function test_dataset_import_dry_run_writes_nothing_then_commit_inserts(): void
    {
        $service = app(DatasetService::class);

        Setting::setValue('brand.tagline', 'No Borders');
        Setting::setValue('brand.name', 'NaaraSim');
        $json = $service->export(['settings']);
        $count = DB::table('settings')->count();
        $this->assertGreaterThanOrEqual(2, $count);

        // Wipe, then a dry-run must report the rows as "new" but write nothing.
        DB::table('settings')->delete();
        $dry = $service->import($json, dryRun: true);
        $this->assertSame($count, $dry['settings']['new']);
        $this->assertSame(0, DB::table('settings')->count()); // untouched

        // Committing actually inserts, inside a transaction.
        $service->import($json, dryRun: false);
        $this->assertSame($count, DB::table('settings')->count());

        // Re-importing is idempotent — everything is now "existing".
        $again = $service->import($json, dryRun: true);
        $this->assertSame(0, $again['settings']['new']);
        $this->assertSame($count, $again['settings']['existing']);
    }

    public function test_importing_a_non_allowlisted_table_is_rejected(): void
    {
        $json = json_encode(['tables' => ['users' => [['id' => 1, 'email' => 'x@y.z']]]]);

        try {
            app(DatasetService::class)->import($json, dryRun: true);
            $this->fail('Expected a non-allowlisted table to be refused.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_restore_requires_a_super_admin(): void
    {
        $this->expectException(HttpException::class);
        app(RestoreService::class)->restore($this->withRole('admin'), 'NaaraSim/x.zip');
    }

    public function test_restore_takes_a_safety_snapshot_before_importing(): void
    {
        Artisan::shouldReceive('call')->andReturn(0); // swallow down/up — no real maintenance mode

        $backups = Mockery::mock(BackupManager::class);
        $backups->shouldReceive('exists')->andReturnTrue();
        $backups->shouldReceive('runNow')->once()->ordered();       // snapshot FIRST

        $restore = Mockery::mock(RestoreService::class, [$backups])->makePartial();
        $restore->shouldAllowMockingProtectedMethods();
        $restore->shouldReceive('importArchive')->once()->ordered(); // …then import

        $restore->restore($this->withRole('super_admin'), 'NaaraSim/x.zip');

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.restored']);
    }
}
