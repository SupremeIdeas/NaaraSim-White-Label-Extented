<?php

namespace Tests\Feature;

use App\Livewire\Admin\Gateways;
use App\Models\User;
use App\Support\GatewayCredentials;
use App\Support\PaymentGatewayConfig;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HOTFIX §4 — dual sandbox/live gateway credentials. Two key sets per card
 * gateway; the Sandbox/Live toggle selects the active one; a legacy single key
 * migrates into the slot matching its test-prefix; public keys are supported
 * (unblocking inline checkout).
 */
class GatewayCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_toggle_selects_which_stored_key_set_is_active(): void
    {
        GatewayCredentials::saveAll([
            'paystack' => [
                'sandbox' => ['secret_key' => 'sk_test_sandbox', 'public_key' => 'pk_test_sb'],
                'live' => ['secret_key' => 'sk_live_real', 'public_key' => 'pk_live_real'],
            ],
        ]);

        PaymentGatewayConfig::setMode('paystack', 'sandbox');
        GatewayCredentials::applyToConfig();
        $this->assertSame('sk_test_sandbox', config('services.paystack.secret_key'));
        $this->assertSame('pk_test_sb', config('services.paystack.public_key'));

        PaymentGatewayConfig::setMode('paystack', 'live');
        GatewayCredentials::applyToConfig();
        $this->assertSame('sk_live_real', config('services.paystack.secret_key'));
        $this->assertSame('pk_live_real', config('services.paystack.public_key'));
    }

    public function test_a_legacy_single_key_migrates_into_the_detected_slot(): void
    {
        // A legacy test key already in config (as ProviderKeys/env would set it).
        config(['services.stripe.secret_key' => 'sk_test_legacy']);

        GatewayCredentials::migrateLegacy();

        // Detected as sandbox by its sk_test prefix.
        $this->assertSame('sk_test_legacy', GatewayCredentials::get('stripe', 'sandbox', 'secret_key'));
        $this->assertSame('', GatewayCredentials::get('stripe', 'live', 'secret_key'));
    }

    public function test_apply_is_additive_and_never_blanks_an_unset_slot(): void
    {
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-legacy']);
        // Nothing stored in the dual matrix for flutterwave.
        GatewayCredentials::applyToConfig();

        // The existing (legacy) key is left untouched — additive, no regression.
        $this->assertSame('FLWSECK_TEST-legacy', config('services.flutterwave.secret_key'));
    }

    public function test_admin_can_save_keys_without_echoing_secrets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Gateways::class)
            ->set('creds.paystack.live.secret_key', 'sk_live_typed')
            ->call('saveCredentials')
            ->assertHasNoErrors();

        $this->assertSame('sk_live_typed', GatewayCredentials::get('paystack', 'live', 'secret_key'));
        // Only a masked preview is ever exposed.
        $this->assertStringNotContainsString('sk_live_typed', (string) GatewayCredentials::preview('paystack', 'live', 'secret_key'));
    }
}
