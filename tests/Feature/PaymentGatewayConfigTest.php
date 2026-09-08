<?php

namespace Tests\Feature;

use App\Livewire\Admin\Gateways;
use App\Models\User;
use App\Support\PaymentGatewayConfig;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BUILD-2 §3 — per-gateway admin: sandbox/live mode (base-URL swap where hosts
 * differ), the webhook/callback URLs, and the live test-connection.
 */
class PaymentGatewayConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_mode_defaults_to_live_and_persists(): void
    {
        $this->assertSame('live', PaymentGatewayConfig::mode('paypal'));
        PaymentGatewayConfig::setMode('paypal', 'sandbox');
        $this->assertSame('sandbox', PaymentGatewayConfig::mode('paypal'));
    }

    public function test_sandbox_mode_swaps_the_base_url_for_distinct_host_gateways(): void
    {
        PaymentGatewayConfig::setMode('paypal', 'sandbox');
        PaymentGatewayConfig::setMode('nowpayments', 'sandbox');
        PaymentGatewayConfig::applyToConfig();

        $this->assertStringContainsString('sandbox', (string) config('services.paypal.base_url'));
        $this->assertStringContainsString('sandbox', (string) config('services.nowpayments.base_url'));

        PaymentGatewayConfig::setMode('paypal', 'live');
        PaymentGatewayConfig::applyToConfig();
        $this->assertSame('https://api-m.paypal.com', config('services.paypal.base_url'));
    }

    public function test_webhook_and_callback_urls_point_at_our_endpoints(): void
    {
        $this->assertStringEndsWith('/webhooks/payments/stripe', PaymentGatewayConfig::webhookUrl('stripe'));
        $this->assertStringEndsWith('/wallet', PaymentGatewayConfig::callbackUrl());
    }

    public function test_test_connection_reports_success_and_failure(): void
    {
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co/balance' => Http::sequence()
            ->push(['status' => true], 200)
            ->push(['status' => false], 401)]);

        $this->assertTrue(PaymentGatewayConfig::testConnection('paystack')['ok']);
        $this->assertFalse(PaymentGatewayConfig::testConnection('paystack')['ok']);
    }

    public function test_non_testable_gateways_report_manual(): void
    {
        $result = PaymentGatewayConfig::testConnection('cryptomus');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsStringIgnoringCase('sign', $result['message']);
    }

    public function test_page_is_admin_only_and_toggles_mode(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/gateways')->assertNotFound();

        $this->actingAs($this->admin())->get('/adminmaster/gateways')->assertOk();

        Livewire::actingAs($this->admin())->test(Gateways::class)
            ->call('setMode', 'stripe', 'sandbox')
            ->assertHasNoErrors();
        $this->assertSame('sandbox', PaymentGatewayConfig::mode('stripe'));
    }

    public function test_page_test_button_populates_a_probe_result(): void
    {
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co/balance' => Http::response(['status' => true], 200)]);

        Livewire::actingAs($this->admin())->test(Gateways::class)
            ->call('test', 'paystack')
            ->assertSet('probe.paystack.ok', true);
    }
}
