<?php

namespace Tests\Feature;

use App\Jobs\ApplyUpdateJob;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreService;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Batch 5 §4 — the white-label-only hook added to the shared ApplyUpdateJob:
 * after a queued apply finishes (success or rollback), it reports the outcome
 * back to the original platform. Proves the report actually fires with the
 * right mapped status, using the exact throwaway-app-root pattern
 * UpdateApplierTest already established (so this never touches the real
 * filesystem) and invoking handle() directly so the job runs synchronously,
 * regardless of the queue driver.
 */
class ApplyUpdateJobReportTest extends TestCase
{
    use RefreshDatabase;

    private string $appRoot;

    private string $pkgDir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appRoot = storage_path('app/testing/jobreport-approot-'.uniqid());
        $this->pkgDir = storage_path('app/testing/jobreport-pkgs-'.uniqid());
        File::ensureDirectoryExists($this->appRoot.'/app');
        File::ensureDirectoryExists($this->appRoot.'/database/migrations');
        File::ensureDirectoryExists($this->pkgDir);

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.original_platform_base_url', 'https://master.example.test');
        config()->set('updater.api_token', 'tok');

        Http::fake(['master.example.test/*' => Http::response(['message' => 'Recorded.'])]);
    }

    protected function tearDown(): void
    {
        Artisan::call('up');
        File::deleteDirectory($this->appRoot);
        File::deleteDirectory($this->pkgDir);
        Mockery::close();
        parent::tearDown();
    }

    private function mockBackups(): void
    {
        $backups = Mockery::mock(BackupManager::class);
        $backups->shouldReceive('runNow')->andReturnNull();
        $backups->shouldReceive('list')->andReturn([['path' => 'snap.zip', 'name' => 'snap.zip', 'size' => 1, 'last_modified' => time()]]);
        app()->instance(BackupManager::class, $backups);
    }

    public function test_a_successful_apply_reports_applied(): void
    {
        $this->mockBackups();
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->never();
        app()->instance(RestoreService::class, $restore);

        $path = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'files' => [['path' => 'app/New.php', 'action' => 'add', 'content' => '<?php // new']],
        ]);

        $applier = app(UpdateApplier::class)->forApplicationRoot($this->appRoot);
        (new ApplyUpdateJob($path, null))->handle($applier);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'report') && $r['status'] === 'applied');
    }

    public function test_a_rolled_back_apply_reports_rolled_back(): void
    {
        $this->mockBackups();
        $restore = Mockery::mock(RestoreService::class);
        $restore->shouldReceive('importArchive')->once();
        app()->instance(RestoreService::class, $restore);

        $path = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'files' => [
                ['path' => 'database/migrations/2026_09_08_150000_boom.php', 'action' => 'add', 'content' =>
                    "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class extends Migration { public function up(): void { throw new \\RuntimeException('boom'); } public function down(): void {} };\n"],
            ],
        ]);

        $applier = app(UpdateApplier::class)->forApplicationRoot($this->appRoot);
        (new ApplyUpdateJob($path, null))->handle($applier);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'report') && $r['status'] === 'rolled_back');
    }
}
