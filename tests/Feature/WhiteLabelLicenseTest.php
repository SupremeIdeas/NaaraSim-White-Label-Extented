<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 6 — the License Authority. Proves the two-credential model end to end:
 * an admin-issued license KEY is exchanged, exactly once and only while live, for
 * a rotatable Sanctum API TOKEN that actually reaches the distribution API; a
 * revoked key is permanently dead; suspension cuts the token but the same key
 * revives on restore; and the public activate endpoint is not a brute-force
 * oracle (unknown, revoked, and suspended keys are indistinguishable from
 * outside). The whole enrolment surface stays invisible until the API is enabled.
 */
class WhiteLabelLicenseTest extends TestCase
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

    private function licensed(string $tier = WhiteLabelInstance::TIER_NORMAL): WhiteLabelInstance
    {
        $instance = $this->service()->register(['brand_name' => 'Acme', 'contact_email' => 'a@acme.test']);

        return $this->service()->issueLicense($instance, $tier);
    }

    // --- Service: issuance ---

    public function test_issuing_a_license_activates_the_instance_with_a_keyed_tier(): void
    {
        $instance = $this->licensed(WhiteLabelInstance::TIER_EXTENDED);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->status);
        $this->assertSame('extended', $instance->tier);
        $this->assertNotNull($instance->license_key);
        $this->assertMatchesRegularExpression('/^NAARA-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $instance->license_key);
        $this->assertNotNull($instance->license_issued_at);
        $this->assertNull($instance->license_revoked_at);
    }

    public function test_registration_alone_creates_a_pending_instance_with_no_key(): void
    {
        $instance = $this->service()->register(['brand_name' => 'Pending Co', 'contact_email' => 'p@co.test']);

        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
        $this->assertNull($instance->license_key);
        $this->assertFalse($instance->usable());
    }

    public function test_license_keys_are_unique_across_instances(): void
    {
        $keys = collect(range(1, 20))->map(fn () => $this->licensed()->license_key);

        $this->assertCount(20, $keys->unique());
    }

    // --- Service: the key -> token exchange ---

    public function test_activating_with_a_valid_key_mints_a_working_token(): void
    {
        $instance = $this->licensed();

        $result = $this->service()->activateWithKey($instance->license_key, ['current_platform_version' => '2026.09.01-1']);

        $this->assertNotEmpty($result['token']);
        $this->assertStringContainsString('|', $result['token']); // Sanctum {id}|{secret}
        $this->assertSame(substr($result['token'], -4), $instance->fresh()->api_token_last_four);
        $this->assertSame('2026.09.01-1', $instance->fresh()->current_platform_version);
    }

    public function test_a_revoked_key_can_never_be_activated_again(): void
    {
        $instance = $this->licensed();
        $key = $instance->license_key;

        $this->service()->revokeLicense($instance);

        $this->expectException(\App\Exceptions\LicenseActivationException::class);
        $this->service()->activateWithKey($key);
    }

    public function test_a_suspended_instance_key_is_refused_but_revives_on_restore(): void
    {
        $instance = $this->licensed();
        $key = $instance->license_key;

        $this->service()->suspend($instance);
        $this->assertSame(0, $instance->fresh()->tokens()->count(), 'suspension kills existing tokens');

        try {
            $this->service()->activateWithKey($key);
            $this->fail('a suspended instance should refuse activation');
        } catch (\App\Exceptions\LicenseActivationException $e) {
            // expected
        }

        // Restore, then the SAME key works again.
        $this->service()->restore($instance->fresh());
        $result = $this->service()->activateWithKey($key);
        $this->assertNotEmpty($result['token']);
    }

    public function test_regenerating_a_key_kills_the_token_minted_from_the_old_one(): void
    {
        $instance = $this->licensed();
        $this->service()->activateWithKey($instance->license_key);
        $this->assertSame(1, $instance->fresh()->tokens()->count());
        $oldKey = $instance->fresh()->license_key;

        // Re-issue (regenerate): new key, old token gone.
        $this->service()->issueLicense($instance->fresh(), $instance->tier);
        $this->assertSame(0, $instance->fresh()->tokens()->count());
        $this->assertNotSame($oldKey, $instance->fresh()->license_key);
    }

    public function test_direct_token_issuance_requires_a_live_license_and_active_status(): void
    {
        $pending = $this->service()->register(['brand_name' => 'X', 'contact_email' => 'x@x.test']);

        $this->expectException(\App\Exceptions\LicenseActivationException::class);
        $this->service()->issueTokenDirectly($pending);
    }

    // --- Public API: register ---

    public function test_register_endpoint_files_a_pending_request_and_reveals_nothing(): void
    {
        $this->enable();

        $res = $this->postJson('/api/v1/white-label/register', [
            'brand_name' => 'Requester Ltd',
            'contact_email' => 'req@ltd.test',
        ]);

        $res->assertStatus(202)->assertJsonMissingPath('token')->assertJsonMissingPath('license_key');
        $this->assertDatabaseHas('white_label_instances', ['contact_email' => 'req@ltd.test', 'status' => 'pending']);
    }

    public function test_the_enrolment_surface_is_invisible_until_the_api_is_enabled(): void
    {
        // Flag off (default): both enrolment endpoints 404, not 405/422 — the
        // surface must not advertise its existence.
        $this->postJson('/api/v1/white-label/register', ['brand_name' => 'a', 'contact_email' => 'a@a.test'])->assertStatus(404);
        $this->postJson('/api/v1/white-label/activate', ['license_key' => 'x'])->assertStatus(404);
    }

    // --- Public API: activate ---

    public function test_activate_endpoint_exchanges_a_key_for_a_token_that_reaches_the_api(): void
    {
        $this->enable();
        $instance = $this->licensed();

        $res = $this->postJson('/api/v1/white-label/activate', [
            'license_key' => $instance->license_key,
            'current_version' => '2026.09.01-1',
        ]);

        $res->assertOk()->assertJsonStructure(['token', 'instance' => ['brand_name', 'slug', 'tier', 'status']]);
        $token = $res->json('token');

        // The minted token actually authenticates against the distribution API.
        $this->withToken($token)
            ->getJson('/api/v1/white-label/updates/check?current_version=2026.09.01-1&product=naarasim-whitelabel')
            ->assertOk();
    }

    public function test_activate_with_an_unknown_key_returns_a_generic_403(): void
    {
        $this->enable();

        $this->postJson('/api/v1/white-label/activate', ['license_key' => 'NAARA-ZZZZ-ZZZZ-ZZZZ'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Invalid or inactive license key.']);
    }

    public function test_activate_with_a_revoked_key_looks_identical_to_an_unknown_one(): void
    {
        $this->enable();
        $instance = $this->licensed();
        $key = $instance->license_key;
        $this->service()->revokeLicense($instance);

        // Same status and same body as an unknown key — no oracle.
        $this->postJson('/api/v1/white-label/activate', ['license_key' => $key])
            ->assertStatus(403)
            ->assertJson(['message' => 'Invalid or inactive license key.']);
    }
}
