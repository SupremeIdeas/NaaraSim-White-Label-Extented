<?php

namespace Tests\Feature;

use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Support\FeatureLocks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch 8 — the master hands each fork its feature-lock list (via the activate
 * response and the authenticated entitlement endpoint), and the admin controls
 * both a fork's level and the per-level lock config from the registry screen.
 */
class FeatureEntitlementApiTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        Setting::setValue('white_label_api.enabled', true);
    }

    private function service(): WhiteLabelLicenseService
    {
        return app(WhiteLabelLicenseService::class);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function licensed(string $tier): WhiteLabelInstance
    {
        $instance = $this->service()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test']);

        return $this->service()->issueLicense($instance, $tier);
    }

    // --- activate response carries the entitlement ---

    public function test_activation_returns_the_forks_lock_list(): void
    {
        $this->enable();
        $instance = $this->licensed(WhiteLabelInstance::TIER_NORMAL);

        $res = $this->postJson('/api/v1/white-label/activate', ['license_key' => $instance->license_key]);

        $res->assertOk()
            ->assertJsonPath('entitlement.level', 'basic')
            ->assertJsonPath('entitlement.locks', fn ($locks) => in_array(FeatureLocks::F_GIFT_CARDS, $locks, true)
                && in_array(FeatureLocks::F_BRAND_HUNT, $locks, true));
    }

    public function test_an_extended_activation_returns_an_empty_lock_list(): void
    {
        $this->enable();
        $instance = $this->licensed(WhiteLabelInstance::TIER_EXTENDED);

        $this->postJson('/api/v1/white-label/activate', ['license_key' => $instance->license_key])
            ->assertOk()
            ->assertJsonPath('entitlement.level', 'full')
            ->assertJsonPath('entitlement.locks', []);
    }

    // --- authenticated entitlement endpoint ---

    public function test_the_entitlement_endpoint_returns_the_instances_current_locks(): void
    {
        $this->enable();
        $instance = $this->licensed(WhiteLabelInstance::TIER_NORMAL);
        $token = $instance->createToken('t', WhiteLabelInstance::SCOPES)->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/white-label/entitlement')
            ->assertOk()
            ->assertJsonPath('level', 'basic')
            ->assertJsonPath('locks', fn ($locks) => in_array(FeatureLocks::F_ESIM_VOICE, $locks, true));
    }

    public function test_the_entitlement_endpoint_reflects_a_level_change(): void
    {
        $this->enable();
        $instance = $this->licensed(WhiteLabelInstance::TIER_NORMAL);
        $this->service()->setEntitlementLevel($instance, WhiteLabelInstance::LEVEL_STANDARD);
        $token = $instance->fresh()->createToken('t', WhiteLabelInstance::SCOPES)->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/white-label/entitlement')
            ->assertOk()
            ->assertJsonPath('level', 'standard')
            ->assertJsonPath('locks', [FeatureLocks::F_GIFT_CARDS]);
    }

    public function test_the_entitlement_endpoint_requires_a_scoped_token(): void
    {
        $this->enable();
        $instance = $this->licensed(WhiteLabelInstance::TIER_NORMAL);
        $token = $instance->createToken('t', [])->plainTextToken; // no scopes

        $this->withToken($token)->getJson('/api/v1/white-label/entitlement')->assertStatus(403);
    }

    // --- admin screen ---

    public function test_an_admin_can_raise_a_forks_level_from_the_registry(): void
    {
        $instance = $this->licensed(WhiteLabelInstance::TIER_NORMAL);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('setLevel', $instance->id, 'standard');

        $this->assertSame('standard', $instance->fresh()->entitlement_level);
    }

    public function test_an_admin_can_toggle_a_feature_lock_at_a_level(): void
    {
        // Brand hunt is locked at basic by default — untoggle it.
        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('toggleFeatureLock', 'basic', FeatureLocks::F_BRAND_HUNT);

        $this->assertNotContains(FeatureLocks::F_BRAND_HUNT, FeatureLocks::locksFor('basic'));
    }

    public function test_a_non_admin_cannot_toggle_a_feature_lock(): void
    {
        $this->seed(RoleSeeder::class);
        Livewire::actingAs(User::factory()->create())->test(WhiteLabelRegistry::class)->assertStatus(403);
    }
}
