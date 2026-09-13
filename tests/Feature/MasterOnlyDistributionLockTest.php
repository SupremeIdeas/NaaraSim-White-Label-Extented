<?php

namespace Tests\Feature;

use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\DistributedPackage;
use App\Models\User;
use App\Services\Updater\PackageBuilder;
use App\Services\Updater\PackagePublisher;
use App\Support\UpdateManifest;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master-Only Distribution Lock — a structural restriction, not just an
 * unchecked toggle. A package built `--master-only` can NEVER be published to
 * white label, enforced server-side in PackagePublisher regardless of what an
 * admin action (or a direct wire:click) asks for. Applying it on the master
 * itself (Admin\Updater) is a completely separate, unaffected code path.
 */
class MasterOnlyDistributionLockTest extends TestCase
{
    use RefreshDatabase;

    private string $workDir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->workDir = storage_path('app/testing/master-only-'.uniqid());
        File::ensureDirectoryExists($this->workDir);

        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.product_identifier', 'naarasim-core');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);
        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function buildPackage(array $overrides = []): string
    {
        return app(PackageBuilder::class)->build(array_merge([
            'output_dir' => $this->workDir,
            'private_key' => $this->keys['private'],
            'changelog' => 'Naara Pro feature.',
            'files' => [
                ['path' => 'app/Services/Pro/DummyProService.php', 'action' => 'add', 'content' => "<?php\n// pro\n"],
            ],
        ], $overrides));
    }

    // --- UpdateManifest / PackageBuilder: the flag actually reaches the manifest ---

    public function test_a_package_built_without_the_flag_is_distributable_by_default(): void
    {
        $path = $this->buildPackage();
        $result = app(\App\Services\Updater\PackageVerifier::class)->verify($path);

        $this->assertTrue($result->passed);
        $this->assertFalse($result->manifest->isMasterOnly());
        $this->assertSame(UpdateManifest::SCOPE_DISTRIBUTABLE, $result->manifest->distributionScope);
    }

    public function test_a_package_built_master_only_carries_that_scope_in_its_manifest(): void
    {
        $path = $this->buildPackage(['distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY]);
        $result = app(\App\Services\Updater\PackageVerifier::class)->verify($path);

        $this->assertTrue($result->passed);
        $this->assertTrue($result->manifest->isMasterOnly());
    }

    public function test_an_older_manifest_with_no_distribution_scope_field_defaults_to_distributable(): void
    {
        $manifest = UpdateManifest::fromArray([
            'package_id' => 'p1', 'product' => 'naarasim-core', 'version' => '2026.09.10-1',
            'min_compatible_version' => '2026.09.01-1', 'package_type' => 'code',
        ]);

        $this->assertFalse($manifest->isMasterOnly());
        $this->assertSame(UpdateManifest::SCOPE_DISTRIBUTABLE, $manifest->distributionScope);
    }

    // --- PackagePublisher: the real, server-side gate ---

    public function test_register_forces_is_published_false_for_a_master_only_package_even_when_publish_true_was_requested(): void
    {
        $path = $this->buildPackage(['distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY]);

        $row = app(PackagePublisher::class)->register($path, publish: true);

        $this->assertFalse($row->is_published);
        $this->assertTrue($row->isMasterOnly());
    }

    public function test_register_publishes_a_distributable_package_normally(): void
    {
        $path = $this->buildPackage();

        $row = app(PackagePublisher::class)->register($path, publish: true);

        $this->assertTrue($row->is_published);
        $this->assertFalse($row->isMasterOnly());
    }

    public function test_set_published_throws_for_a_master_only_package(): void
    {
        $path = $this->buildPackage(['distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY]);
        $row = app(PackagePublisher::class)->register($path);

        $this->expectException(\RuntimeException::class);
        app(PackagePublisher::class)->setPublished($row, true);
    }

    public function test_set_published_still_allows_unpublishing_a_master_only_package(): void
    {
        // A master-only row is already unpublished by register(), but
        // setPublished(false) must never throw regardless — only the
        // publish=true direction is restricted.
        $row = DistributedPackage::create([
            'package_id' => 'pro-'.uniqid(), 'product' => 'naarasim-core', 'version' => '2026.09.10-1',
            'package_type' => 'code', 'min_compatible_version' => '2026.09.01-1',
            'distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY,
            'storage_path' => 'distribution/x.naaraupdate', 'size_bytes' => 10, 'is_published' => false,
        ]);

        app(PackagePublisher::class)->setPublished($row, false);
        $this->assertFalse($row->fresh()->is_published);
    }

    // --- Admin UI: locked badge, no clickable Publish, Withdraw still works ---

    public function test_the_admin_screen_shows_a_locked_badge_with_no_publish_button(): void
    {
        $row = DistributedPackage::create([
            'package_id' => 'pro-'.uniqid(), 'product' => 'naarasim-core', 'version' => '2026.09.10-1',
            'package_type' => 'code', 'min_compatible_version' => '2026.09.01-1',
            'distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY,
            'storage_path' => 'distribution/x.naaraupdate', 'size_bytes' => 10, 'is_published' => false,
        ]);

        Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class)
            ->assertSee('Master-only')
            ->assertSee('Cannot be distributed')
            ->assertDontSee('wire:click="togglePublish('.$row->id.')"', false);
    }

    public function test_a_direct_toggle_publish_call_on_a_master_only_package_is_rejected_cleanly(): void
    {
        // Defense in depth: even a wire:click bypassing the UI must not crash
        // the component or silently succeed — it gets a clean error toast.
        $row = DistributedPackage::create([
            'package_id' => 'pro-'.uniqid(), 'product' => 'naarasim-core', 'version' => '2026.09.10-1',
            'package_type' => 'code', 'min_compatible_version' => '2026.09.01-1',
            'distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY,
            'storage_path' => 'distribution/x.naaraupdate', 'size_bytes' => 10, 'is_published' => false,
        ]);

        Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class)
            ->call('togglePublish', $row->id)
            ->assertDispatched('nx-toast', type: 'error');

        $this->assertFalse($row->fresh()->is_published);
    }

    public function test_withdraw_still_works_on_a_master_only_package(): void
    {
        $row = DistributedPackage::create([
            'package_id' => 'pro-'.uniqid(), 'product' => 'naarasim-core', 'version' => '2026.09.10-1',
            'package_type' => 'code', 'min_compatible_version' => '2026.09.01-1',
            'distribution_scope' => UpdateManifest::SCOPE_MASTER_ONLY,
            'storage_path' => 'distribution/x.naaraupdate', 'size_bytes' => 10, 'is_published' => false,
        ]);

        Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class)
            ->call('withdrawPackage', $row->id);

        $this->assertNull(DistributedPackage::find($row->id));
    }

}
