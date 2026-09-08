<?php

namespace Tests\Feature;

use App\Models\ProviderRegistry;
use App\Services\eSIM\EsimProviderInterface;
use App\Services\GiftCards\GiftCardProviderInterface;
use App\Services\SMS\NumberProviderInterface;
use App\Services\SMS\SmsProviderInterface;
use Database\Seeders\ProviderExpansionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NAARA-BUILD-18 — the new adapters implement the EXISTING interfaces, resolve
 * through the container bindings, work against faked HTTP, and register into the
 * Operations Center shipped disabled.
 */
class ProviderExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_adapters_implement_the_existing_interfaces(): void
    {
        $this->assertInstanceOf(SmsProviderInterface::class, app('number.smspool'));
        $this->assertInstanceOf(SmsProviderInterface::class, app('number.onlinesim'));
        $this->assertInstanceOf(NumberProviderInterface::class, app('number.plivo'));
        $this->assertInstanceOf(NumberProviderInterface::class, app('number.sonetel'));
        $this->assertInstanceOf(EsimProviderInterface::class, app('esim.esimaccess'));
        $this->assertInstanceOf(EsimProviderInterface::class, app('esim.ubigi'));

        $gift = app(\App\Services\GiftCards\GiftCardCatalogueSyncService::class);
        $this->assertInstanceOf(GiftCardProviderInterface::class, $gift->provider('bitrefill'));
        $this->assertInstanceOf(GiftCardProviderInterface::class, $gift->provider('tillo'));
    }

    public function test_smspool_prices_and_buys_against_faked_http(): void
    {
        config(['services.smspool.api_key' => 'k']);
        Http::fake([
            '*/request/price' => Http::response(['price' => 0.35]),
            '*/purchase/sms' => Http::response(['success' => 1, 'order_id' => 'A1', 'phonenumber' => '+15551230000']),
            '*/request/balance' => Http::response(['balance' => 42.0]),
        ]);

        $svc = app('number.smspool');
        $this->assertSame(0.35, $svc->priceFor('US', 'google'));
        $buy = $svc->buyOtp('US', 'google', ['max_price' => 1]);
        $this->assertSame('A1', $buy['provider_ref']);
        $this->assertSame('+15551230000', $buy['number']);
        $this->assertSame(42.0, $svc->balance());
    }

    public function test_esim_access_orders_a_bundle_against_faked_http(): void
    {
        config(['services.esimaccess.api_key' => 'k']);
        Http::fake(['*/open/esim/order' => Http::response(['success' => true, 'obj' => ['orderNo' => 'O9', 'iccid' => '8910', 'ac' => 'LPA:1$x']])]);

        $res = app('esim.esimaccess')->orderBundle('PKG-1');
        $this->assertSame('O9', $res['provider_ref']);
        $this->assertSame('8910', $res['iccid']);
    }

    public function test_the_new_providers_register_disabled_in_the_operations_center(): void
    {
        $this->seed(ProviderExpansionSeeder::class);

        foreach (['smspool', 'onlinesim', 'plivo', 'bitrefill', 'esimaccess', 'tillo', 'ubigi', 'sonetel'] as $key) {
            $row = ProviderRegistry::where('provider_key', $key)->first();
            $this->assertNotNull($row, "$key should be registered");
            $this->assertFalse((bool) $row->enabled, "$key must ship disabled");
        }

        // Tier positioning: self-service providers sort ahead of placeholder tiers.
        $this->assertSame('self_service', ProviderRegistry::where('provider_key', 'smspool')->value('onboarding_tier'));
        $this->assertSame('enterprise', ProviderRegistry::where('provider_key', 'tillo')->value('onboarding_tier'));
    }
}
