<?php

namespace Tests\Feature;

use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\DistributedPackage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackagePublisher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 4 §2 — the White-Label Oversight admin screen. Gate, the feature-flag
 * toggle, per-brand API-log drill-down, and publisher-side publish/unpublish.
 */
class WhiteLabelRegistryScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function package(bool $published = false): DistributedPackage
    {
        return DistributedPackage::create([
            'package_id' => 'pkg-'.uniqid(),
            'product' => 'naarasim-whitelabel',
            'version' => '2026.09.10-1',
            'package_type' => 'code_and_migrations',
            'min_compatible_version' => '2026.09.01-1',
            'storage_path' => 'distribution/x.naaraupdate',
            'size_bytes' => 100,
            'is_published' => $published,
        ]);
    }

    public function test_a_non_admin_gets_a_403(): void
    {
        Livewire::actingAs(User::factory()->create())->test(WhiteLabelRegistry::class)->assertStatus(403);
    }

    public function test_an_admin_can_toggle_the_api_feature_flag(): void
    {
        $this->assertFalse((bool) Setting::getValue('white_label_api.enabled', false));

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('toggleApi');

        $this->assertTrue((bool) Setting::getValue('white_label_api.enabled', false));
    }

    public function test_publish_and_unpublish_a_package(): void
    {
        $package = $this->package(published: false);

        $c = Livewire::actingAs($this->admin())->test(WhiteLabelRegistry::class);

        $c->call('togglePublish', $package->id);
        $this->assertTrue($package->fresh()->is_published);

        $c->call('togglePublish', $package->id);
        $this->assertFalse($package->fresh()->is_published);
    }

    public function test_withdraw_deletes_the_package_and_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('distribution/x.naaraupdate', 'bytes');
        $package = $this->package(published: true);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('withdrawPackage', $package->id);

        $this->assertNull(DistributedPackage::find($package->id));
        Storage::disk('local')->assertMissing('distribution/x.naaraupdate');
    }

    public function test_the_per_brand_log_drilldown_shows_that_brands_calls(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Brand', 'slug' => 'brand', 'contact_email' => 'a@b.c', 'status' => 'active',
        ]);
        WhiteLabelApiLog::create([
            'white_label_instance_id' => $instance->id, 'endpoint' => 'white-label.updates.check',
            'method' => 'GET', 'response_status' => 200, 'created_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->assertSet('selectedInstanceId', null)
            ->call('selectInstance', $instance->id)
            ->assertSet('selectedInstanceId', $instance->id)
            ->assertSee('white-label.updates.check');
    }

    // --- Batch 6: license authority admin controls ---

    public function test_issuing_a_license_creates_an_active_instance_and_reveals_the_key(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('newBrand', 'Fresh Brand')
            ->set('newEmail', 'fresh@brand.test')
            ->set('newTier', 'extended')
            ->call('issueNewLicense')
            ->assertSet('revealedKey', fn ($k) => is_string($k) && str_starts_with($k, 'NAARA-'));

        $instance = WhiteLabelInstance::where('contact_email', 'fresh@brand.test')->first();
        $this->assertNotNull($instance);
        $this->assertSame('active', $instance->status);
        $this->assertSame('extended', $instance->tier);
    }

    public function test_approving_a_pending_request_issues_a_key_and_activates_it(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Waiting', 'slug' => 'waiting', 'contact_email' => 'w@w.test', 'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveInstance', $instance->id, 'normal')
            ->assertSet('revealedKey', fn ($k) => is_string($k) && str_starts_with($k, 'NAARA-'));

        $this->assertSame('active', $instance->fresh()->status);
        $this->assertSame('normal', $instance->fresh()->tier);
    }

    public function test_suspending_from_the_screen_revokes_the_token(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Live', 'slug' => 'live', 'contact_email' => 'l@l.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-AAAA-BBBB-CCCC', 'license_issued_at' => now(),
        ]);
        $instance->createToken('t', WhiteLabelInstance::SCOPES);
        $this->assertSame(1, $instance->tokens()->count());

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('suspendInstance', $instance->id);

        $this->assertSame('suspended', $instance->fresh()->status);
        $this->assertSame(0, $instance->fresh()->tokens()->count());
    }

    public function test_revoking_from_the_screen_permanently_kills_the_key(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Gone', 'slug' => 'gone', 'contact_email' => 'g@g.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-DDDD-EEEE-FFFF', 'license_issued_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('revokeLicense', $instance->id);

        $this->assertNotNull($instance->fresh()->license_revoked_at);
        $this->assertSame('suspended', $instance->fresh()->status);
    }

    public function test_issue_token_directly_reveals_a_bearer_token_once(): void
    {
        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Firewalled', 'slug' => 'fw', 'contact_email' => 'f@f.test', 'status' => 'active',
            'tier' => 'normal', 'license_key' => 'NAARA-GGGG-HHHH-JJJJ', 'license_issued_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('issueTokenFor', $instance->id)
            ->assertSet('revealedToken', fn ($t) => is_string($t) && str_contains($t, '|'));

        $this->assertSame(1, $instance->fresh()->tokens()->count());
    }
}
