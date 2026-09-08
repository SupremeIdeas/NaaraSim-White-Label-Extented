<?php

namespace Tests\Feature;

use App\Jobs\ApplyUpdateJob;
use App\Livewire\Admin\Updater;
use App\Models\User;
use App\Services\Updater\PackageBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 2 §4 — the admin Updater screen. Proves the gate (super_admin only),
 * the verify-before-apply confirmation moment, and that Apply hands off to the
 * queued job rather than doing the work inline.
 */
class UpdaterScreenTest extends TestCase
{
    use RefreshDatabase;

    private array $keys;

    private string $pkgDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);

        $this->pkgDir = storage_path('app/testing/screen-'.uniqid());
        File::ensureDirectoryExists($this->pkgDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pkgDir);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function validUpload(): UploadedFile
    {
        $path = app(PackageBuilder::class)->build([
            'output_dir' => $this->pkgDir,
            'private_key' => $this->keys['private'],
            'changelog' => 'screen test',
            'files' => [['path' => 'app/Foo.php', 'action' => 'add', 'content' => '<?php // foo']],
        ]);

        return UploadedFile::fake()->createWithContent('update.naaraupdate', File::get($path));
    }

    public function test_a_non_super_admin_cannot_open_the_updater(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(Updater::class)
            ->assertStatus(403);
    }

    public function test_a_super_admin_verifies_a_package_then_queues_the_apply(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('package', $this->validUpload())
            ->call('verifyPackage')
            ->assertSet('verifyError', null)
            ->assertSet('verified.product', 'naarasim-core')
            ->assertSet('verified.files', 1)
            ->call('applyPackage')
            ->assertSet('status', fn ($s) => str_contains((string) $s, 'queued'));

        Queue::assertPushed(ApplyUpdateJob::class);
    }

    public function test_a_tampered_upload_is_rejected_and_not_queued(): void
    {
        Queue::fake();

        // Wrong public key configured → signature can't verify.
        config()->set('updater.public_key', PackageBuilder::generateKeypair()['public']);

        Livewire::actingAs($this->user('super_admin'))
            ->test(Updater::class)
            ->set('package', $this->validUpload())
            ->call('verifyPackage')
            ->assertSet('verified', null)
            ->assertSet('verifyError', fn ($e) => $e !== null);

        Queue::assertNotPushed(ApplyUpdateJob::class);
    }
}
