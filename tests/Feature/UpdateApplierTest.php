<?php

namespace Tests\Feature;

use App\Models\PlatformUpdateAttempt;
use App\Models\Setting;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreService;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Batch 2 — the apply engine. These prove the one property the whole design
 * exists for: a good package applies cleanly, and ANY failure (broken migration,
 * a failed file write) returns the platform — files and DB — to its pre-apply
 * state and brings it back up, rather than leaving a half-applied app behind
 * maintenance mode.
 *
 * The applier is pointed at a throwaway app root so tests never touch the real
 * tree, and BackupManager/RestoreService are mocked so no real backup runs — the
 * assertion is that the DB restore is *invoked* on rollback.
 */
class UpdateApplierTest extends TestCase
{
    use RefreshDatabase;

    private string $appRoot;

    private string $pkgDir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appRoot = storage_path('app/testing/approot-'.uniqid());
        $this->pkgDir = storage_path('app/testing/pkgs-'.uniqid());
        File::ensureDirectoryExists($this->appRoot.'/app');
        File::ensureDirectoryExists($this->appRoot.'/database/migrations');
        File::ensureDirectoryExists($this->pkgDir);

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.product_identifier', 'naarasim-core');
    }

    protected function tearDown(): void
    {
        Artisan::call('up'); // never leave the test app in maintenance mode
        File::deleteDirectory($this->appRoot);
        File::deleteDirectory($this->pkgDir);
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<int,array<string,string>>  $files */
    private function buildPackage(array $files, array $overrides = []): string
    {
        return app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'files' => $files,
        ], $overrides));
    }

    private function mockBackups(?string $archive = 'snap.zip'): void
    {
        $backups = Mockery::mock(BackupManager::class);
        $backups->shouldReceive('runNow')->andReturnNull();
        $backups->shouldReceive('list')->andReturn(
            $archive ? [['path' => $archive, 'name' => $archive, 'size' => 1, 'last_modified' => time()]] : []
        );
        app()->instance(BackupManager::class, $backups);
    }

    private function applier(RestoreService $restore): UpdateApplier
    {
        app()->instance(RestoreService::class, $restore);

        return app(UpdateApplier::class)->forApplicationRoot($this->appRoot);
    }

    private function migrationBody(string $className, string $up): string
    {
        return "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { {$up} } public function down(): void { Schema::dropIfExists('dummy_batch2'); } };\n";
    }

    public function test_a_valid_package_applies_and_records_success(): void
    {
        File::put($this->appRoot.'/app/Existing.php', '<?php // old');
        $this->mockBackups();

        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->never();

        $pkg = $this->buildPackage([
            ['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php // new code'],
            ['path' => 'app/Existing.php', 'action' => 'modify', 'content' => '<?php // updated'],
            ['path' => 'database/migrations/2026_09_08_130000_create_dummy_batch2.php', 'action' => 'add',
                'content' => $this->migrationBody('X', "Schema::create('dummy_batch2', function (Blueprint \$t) { \$t->id(); });")],
        ]);

        $attempt = $this->applier($restore)->apply($pkg, null);

        $this->assertSame(PlatformUpdateAttempt::STATUS_SUCCEEDED, $attempt->status);
        $this->assertSame('<?php // new code', File::get($this->appRoot.'/app/New.php'));
        $this->assertSame('<?php // updated', File::get($this->appRoot.'/app/Existing.php'));
        $this->assertTrue(Schema::hasTable('dummy_batch2'), 'migration should have run');
        $this->assertSame($attempt->to_version, Setting::getValue(UpdateApplier::VERSION_SETTING));
        $this->assertSame(1, $attempt->migrations_run_count);
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_a_broken_migration_triggers_full_rollback(): void
    {
        File::put($this->appRoot.'/app/Existing.php', '<?php // old');
        $this->mockBackups();

        // The DB restore MUST be invoked during rollback.
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->once()->with('snap.zip');

        $pkg = $this->buildPackage([
            ['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php // added'],
            ['path' => 'app/Existing.php', 'action' => 'modify', 'content' => '<?php // updated'],
            ['path' => 'database/migrations/2026_09_08_140000_boom.php', 'action' => 'add',
                'content' => $this->migrationBody('B', "throw new \\RuntimeException('boom');")],
        ]);

        $attempt = $this->applier($restore)->apply($pkg, null);

        $this->assertSame(PlatformUpdateAttempt::STATUS_ROLLED_BACK, $attempt->status);
        // File state returned to exactly pre-apply: modified file restored, added file removed.
        $this->assertSame('<?php // old', File::get($this->appRoot.'/app/Existing.php'));
        $this->assertFileDoesNotExist($this->appRoot.'/app/New.php');
        // Version never advanced; app is back up.
        $this->assertNull(Setting::getValue(UpdateApplier::VERSION_SETTING));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_a_failed_file_write_rolls_back(): void
    {
        File::put($this->appRoot.'/app/Existing.php', '<?php // old');
        // A directory sitting where a payload file must be written forces a
        // mid-apply write failure — the pipeline must detect it and roll back.
        File::ensureDirectoryExists($this->appRoot.'/app/Blocked.php');
        $this->mockBackups();

        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->once();

        $pkg = $this->buildPackage([
            ['path' => 'app/Existing.php', 'action' => 'modify', 'content' => '<?php // updated'],
            ['path' => 'app/Blocked.php', 'action' => 'add', 'content' => '<?php // cannot write'],
        ]);

        $attempt = $this->applier($restore)->apply($pkg, null);

        $this->assertSame(PlatformUpdateAttempt::STATUS_ROLLED_BACK, $attempt->status);
        $this->assertStringContainsString('Blocked.php', (string) $attempt->failure_reason);
        $this->assertSame('<?php // old', File::get($this->appRoot.'/app/Existing.php'));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_it_refuses_when_another_apply_is_in_progress(): void
    {
        $this->mockBackups();
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->never();

        $lock = Cache::lock(UpdateApplier::LOCK_KEY, 10);
        $this->assertTrue($lock->get());

        try {
            $pkg = $this->buildPackage([['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php']]);

            $this->expectException(\RuntimeException::class);
            $this->applier($restore)->apply($pkg, null);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, PlatformUpdateAttempt::count());
    }

    public function test_it_refuses_an_incompatible_version(): void
    {
        Setting::setValue(UpdateApplier::VERSION_SETTING, '2026.09.01-1', 'platform');
        $this->mockBackups();
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->never();

        $pkg = $this->buildPackage(
            [['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php']],
            ['min_compatible_version' => '2026.10.01-1'],
        );

        $this->expectException(\RuntimeException::class);

        try {
            $this->applier($restore)->apply($pkg, null);
        } finally {
            $this->assertFileDoesNotExist($this->appRoot.'/app/New.php');
        }
    }

    public function test_it_rejects_a_tampered_package_before_touching_anything(): void
    {
        $this->mockBackups();
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->never();

        $pkg = $this->buildPackage([['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php // ok']]);

        // Corrupt a payload byte after signing.
        $zip = new \ZipArchive;
        $zip->open($pkg);
        $zip->deleteName('payload/app/New.php');
        $zip->addFromString('payload/app/New.php', '<?php // TAMPERED');
        $zip->close();

        $this->expectException(\RuntimeException::class);

        try {
            $this->applier($restore)->apply($pkg, null);
        } finally {
            $this->assertFileDoesNotExist($this->appRoot.'/app/New.php');
            $this->assertSame(0, PlatformUpdateAttempt::count());
        }
    }
}
