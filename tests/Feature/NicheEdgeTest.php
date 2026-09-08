<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\EsimPlan;
use App\Models\User;
use App\Support\Niche\DataEstimator;
use App\Support\Niche\DeviceCompat;
use App\Support\Niche\LpaActivation;
use App\Support\Niche\SupportLinks;
use App\Services\Wallet\WalletService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 21 — Niche Edge Features (blueprint Section 32).
 */
class NicheEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-1', 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00, 'is_active' => true,
        ])->fresh();
    }

    public function test_device_compat_knows_supported_unsupported_and_unknown(): void
    {
        $this->assertTrue(DeviceCompat::check('iPhone 14 Pro'));
        $this->assertTrue(DeviceCompat::check('Samsung Galaxy S23'));
        $this->assertFalse(DeviceCompat::check('iPhone 7'));
        $this->assertNull(DeviceCompat::check('SomeObscurePhone 1'));
    }

    public function test_a_purchase_is_blocked_until_the_device_is_confirmed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        app(WalletService::class)->credit($user, 100, 'USD');

        // No device confirmation -> refused, nothing charged.
        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $this->plan()])
            ->call('purchase')
            ->assertSet('error', 'Please confirm your device supports eSIM before buying.')
            ->assertSet('done', false);

        $this->assertSame(0, \App\Models\EsimOrder::count());
    }

    public function test_checking_a_supported_device_auto_confirms_it(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $this->plan()])
            ->set('device', 'iPhone 15')
            ->call('checkDevice')
            ->assertSet('deviceResult', true)
            ->assertSet('deviceConfirmed', true);
    }

    public function test_lpa_string_is_built_or_extracted_from_a_provider_payload(): void
    {
        // Ready-made LPA string.
        $this->assertSame(
            'LPA:1$smdp.example.com$ABC-123',
            LpaActivation::fromPayload(['activation_code' => 'LPA:1$smdp.example.com$ABC-123'])
        );

        // Built from parts (https:// stripped).
        $this->assertSame(
            'LPA:1$rsp.truphone.com$XYZ',
            LpaActivation::fromPayload(['smdp_address' => 'https://rsp.truphone.com', 'matching_id' => 'XYZ'])
        );

        $this->assertNull(LpaActivation::fromPayload(['nothing' => 'here']));
        $this->assertNotEmpty(LpaActivation::steps());
    }

    public function test_the_data_estimator_scales_with_profile_and_days(): void
    {
        $light = DataEstimator::estimate('light', 10);
        $heavy = DataEstimator::estimate('heavy', 10);

        $this->assertSame(2500, $light['mb']);       // 250 MB/day * 10
        $this->assertGreaterThan($light['mb'], $heavy['mb']);
        $this->assertSame(2.4, $light['gb']);

        Livewire::test(\App\Livewire\DataEstimator::class)
            ->set('profile', 'heavy')->set('days', 5)
            ->assertSee('GB');
    }

    public function test_the_refund_policy_page_loads(): void
    {
        $this->get('/refund-policy')->assertOk()->assertSee('Refund Policy');
    }

    public function test_support_whatsapp_link_is_gated_on_configuration(): void
    {
        config(['naara.support.whatsapp' => '']);
        $this->assertFalse(SupportLinks::hasWhatsapp());
        $this->assertNull(SupportLinks::whatsappUrl());

        config(['naara.support.whatsapp' => '+234 801 234 5678']);
        $this->assertTrue(SupportLinks::hasWhatsapp());
        $this->assertStringContainsString('https://wa.me/2348012345678', SupportLinks::whatsappUrl());
    }
}
