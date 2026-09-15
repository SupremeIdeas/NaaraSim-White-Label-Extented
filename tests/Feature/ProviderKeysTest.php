<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProviderKeys as ProviderKeysComponent;
use App\Models\Setting;
use App\Models\User;
use App\Support\MediaStorage;
use App\Support\ProviderKeys;
use App\Support\ProviderStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-managed API credentials (blueprint Sections 15 & 17.4, money rule 10).
 */
class ProviderKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        ProviderKeys::flush();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_a_saved_key_overrides_config_and_flips_the_provider_active(): void
    {
        config(['services.getatext.api_key' => null]);
        $this->assertSame('Coming Soon', ProviderStatus::label('getatext'));

        ProviderKeys::save(['getatext_api_key' => 'sk_live_admin_saved']);
        ProviderKeys::applyToConfig();

        $this->assertSame('sk_live_admin_saved', config('services.getatext.api_key'));
        $this->assertSame('Active', ProviderStatus::label('getatext'));
    }

    public function test_a_blank_field_leaves_the_stored_key_untouched(): void
    {
        ProviderKeys::save(['fivesim_api_key' => 'jwt-original']);
        ProviderKeys::save(['fivesim_api_key' => '']); // blank = no-op
        ProviderKeys::applyToConfig();

        $this->assertSame('jwt-original', config('services.fivesim.api_key'));
    }

    public function test_keys_are_encrypted_at_rest_and_never_stored_in_plaintext(): void
    {
        ProviderKeys::save(['stripe_secret_key' => 'sk_live_topsecret_value']);

        // The raw settings row (bypassing the Eloquent cast) must not contain
        // the plaintext secret — it is encrypted at rest.
        $raw = DB::table('settings')
            ->where('key', ProviderKeys::SETTING_KEY)
            ->value('value');
        $this->assertStringNotContainsString('sk_live_topsecret_value', (string) $raw);
    }

    public function test_preview_is_masked_and_reveals_only_the_last_four(): void
    {
        ProviderKeys::save(['paystack_secret_key' => 'sk_live_1234ABCD']);
        ProviderKeys::applyToConfig();

        $preview = ProviderKeys::preview('paystack_secret_key');
        $this->assertStringEndsWith('ABCD', $preview);
        $this->assertStringNotContainsString('1234', $preview);
        $this->assertStringContainsString('•', $preview);
    }

    public function test_the_admin_page_saves_keys_and_never_echoes_them_back(): void
    {
        Livewire::actingAs($this->superAdmin())->test(ProviderKeysComponent::class)
            ->set('inputs.esimgo_api_key', 'esimgo-live-key')
            ->call('save')
            ->assertSet('inputs.esimgo_api_key', '') // cleared, not echoed
            ->assertSee('background workers will pick them up'); // queue-restart signalled

        ProviderKeys::applyToConfig();
        $this->assertSame('esimgo-live-key', config('services.esimgo.api_key'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'providers.keys_updated']);
    }

    public function test_a_changed_provider_key_takes_effect_for_catalogue_sync_without_a_restart(): void
    {
        // A worker/process that already booted with an OLD key.
        config([
            'services.esimgo.api_key' => 'OLD-KEY',
            'services.esimgo.base_url' => 'https://api.esim-go.com/v2.5',
        ]);
        Cache::forget('illuminate:queue:restart');
        Http::fake([
            'api.esim-go.com/*' => Http::response(['bundles' => []]),
        ]);

        // Admin pastes a new key. save() overlays config in-process AND signals
        // queue:restart so any long-running worker re-boots with the new key
        // (the esim_upgrade Part 1 stale-config bug).
        ProviderKeys::save(['esimgo_api_key' => 'NEW-LIVE-KEY']);

        // In-process config already reflects it — no restart needed here.
        $this->assertSame('NEW-LIVE-KEY', config('services.esimgo.api_key'));

        // A catalogue fetch now authenticates with the NEW key, not the stale one.
        app('esim.esimgo')->getCatalogue();
        Http::assertSent(
            fn ($r) => $r->hasHeader('X-API-Key', 'NEW-LIVE-KEY'),
        );

        // And long-running workers were signalled to restart.
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }

    public function test_r2_credentials_pasted_in_the_admin_configure_the_r2_disk(): void
    {
        // No R2 anywhere yet.
        config(['filesystems.disks.r2.key' => null, 'filesystems.disks.r2.secret' => null, 'filesystems.disks.r2.bucket' => null, 'filesystems.disks.r2.endpoint' => null]);
        $this->assertFalse(MediaStorage::r2Configured());

        Livewire::actingAs($this->superAdmin())->test(ProviderKeysComponent::class)
            ->set('inputs.r2_access_key_id', 'r2-key')
            ->set('inputs.r2_secret_access_key', 'r2-secret')
            ->set('inputs.r2_bucket', 'naara-media')
            ->set('inputs.r2_endpoint', 'https://acc.r2.cloudflarestorage.com')
            ->set('inputs.r2_public_url', 'https://cdn.naara.test')
            ->call('save')
            ->assertSet('inputs.r2_secret_access_key', ''); // never echoed back

        ProviderKeys::applyToConfig();
        $this->assertTrue(MediaStorage::r2Configured());
        $this->assertSame('naara-media', config('filesystems.disks.r2.bucket'));
        $this->assertSame('r2', MediaStorage::disk()); // now serves public media
    }

    public function test_admin_can_pin_the_primary_media_store(): void
    {
        Livewire::actingAs($this->superAdmin())->test(ProviderKeysComponent::class)
            ->set('primaryDisk', 'wasabi')
            ->call('savePrimaryDisk');

        $this->assertSame('wasabi', Setting::getValue('media.primary_disk'));
        $this->assertSame('wasabi', MediaStorage::primaryPreference());
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.primary_disk_updated']);
    }

    public function test_the_build18_providers_are_configurable_in_the_admin(): void
    {
        // Regression: these providers had adapters + config/services.php entries
        // but were never added to the admin schema, so they were invisible/hidden
        // in Admin → API Keys. Each must now be settable and flip Active.
        $newProviders = [
            'esimaccess' => ['esimaccess_api_key' => 'ea-live'],
            'ubigi' => ['ubigi_api_key' => 'ub-live'],
            'smspool' => ['smspool_api_key' => 'sp-live'],
            'onlinesim' => ['onlinesim_api_key' => 'os-live'],
            'plivo' => ['plivo_auth_id' => 'pl-id', 'plivo_auth_token' => 'pl-tok'],
            'sonetel' => ['sonetel_username' => 'so@example.com', 'sonetel_password' => 'so-pass', 'sonetel_account_id' => 'acc-123'],
            'bitrefill' => ['bitrefill_api_id' => 'br-id', 'bitrefill_api_secret' => 'br-sec'],
            'tillo' => ['tillo_api_key' => 'ti-key', 'tillo_secret' => 'ti-sec'],
        ];

        foreach ($newProviders as $provider => $fields) {
            $this->assertSame('Coming Soon', ProviderStatus::label($provider), "$provider should start Coming Soon");
            ProviderKeys::save($fields);
            ProviderKeys::applyToConfig();
            $this->assertSame('Active', ProviderStatus::label($provider), "$provider should be Active once its key(s) are saved");
        }
    }

    public function test_every_status_tracked_provider_is_settable_in_the_admin(): void
    {
        // Invariant: no provider may show a status badge with no way to enter its
        // key. Every ProviderStatus provider whose credentials live under
        // services.* must be settable through the ProviderKeys schema. WhatsApp is
        // the one documented exception — it has its own admin Integrations page.
        $settable = array_values(ProviderKeys::fieldMap());
        $required = (new \ReflectionClass(ProviderStatus::class))->getConstant('REQUIRED');
        $exceptions = ['whatsapp']; // configured on the admin Integrations page

        foreach ($required as $provider => $paths) {
            if (in_array($provider, $exceptions, true)) {
                continue;
            }
            foreach ($paths as $path) {
                if (str_starts_with($path, 'services.')) {
                    $this->assertContains($path, $settable, "Provider [$provider] shows a status badge but [$path] is not settable in Admin → API Keys.");
                }
            }
        }
    }

    public function test_a_non_super_admin_cannot_reach_the_api_keys_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // super_admin-only route -> 403 for a plain admin.
        $this->actingAs($admin)->get('/adminmaster/api-keys')->assertForbidden();

        // super_admin gets in.
        $this->actingAs($this->superAdmin())->get('/adminmaster/api-keys')->assertOk();
    }
}
