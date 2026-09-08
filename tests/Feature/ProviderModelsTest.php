<?php

namespace Tests\Feature;

use App\Models\EsimOrder;
use App\Models\SmsOrder;
use App\Models\User;
use App\Support\ProviderModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The connectivity "Model" layer — the supplier-masking keystone for the Wizard
 * and the reorganised dashboard. Users see Models (Naara Data/Verify/Rent/Line);
 * the real provider is never exposed.
 */
class ProviderModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_models_exist_and_map_to_the_real_product_types(): void
    {
        $keys = array_column(ProviderModels::all(), 'key');
        $this->assertEqualsCanonicalizing(
            ['naara_data', 'naara_connect', 'naara_verify', 'naara_rent', 'naara_line'],
            $keys,
        );
        $this->assertSame('naara_verify', ProviderModels::forNumberType('otp')['key']);
        $this->assertSame('naara_rent', ProviderModels::forNumberType('rental')['key']);
        $this->assertSame('naara_line', ProviderModels::forNumberType('permanent')['key']);
        $this->assertSame('naara_data', ProviderModels::esim()['key']);
        // Naara Connect (Full eSIM) rides the Zendit voice lane.
        $this->assertSame('naara_connect', ProviderModels::forProvider('zendit')['key']);
    }

    public function test_a_model_needs_a_configured_provider_in_its_lane(): void
    {
        // No keys → every Model needs a key (permanent provisioning is now wired,
        // so Naara Line is key-driven too, not hard "coming soon").
        config(['services.fivesim.api_key' => null, 'services.esimgo.api_key' => null,
                'services.getatext.api_key' => null, 'services.herosms.api_key' => null, 'services.virtsms.api_key' => null,
                'services.twilio.account_sid' => null, 'services.telnyx.api_key' => null]);
        \App\Support\ProviderKeys::flush();
        $this->assertSame('needs_key', ProviderModels::status('naara_verify'));
        $this->assertSame('needs_key', ProviderModels::status('naara_line'));

        // Configure ONE provider in the OTP/rental lane → those Models go live,
        // but Naara Line still needs Twilio/Telnyx.
        config(['services.fivesim.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
        $this->assertSame('live', ProviderModels::status('naara_verify'));
        $this->assertSame('live', ProviderModels::status('naara_rent'));
        $this->assertSame('needs_key', ProviderModels::status('naara_line'));

        // Configure Twilio → Naara Line goes live.
        config(['services.twilio.account_sid' => 'AC', 'services.twilio.auth_token' => 'tok']);
        \App\Support\ProviderKeys::flush();
        $this->assertSame('live', ProviderModels::status('naara_line'));
    }

    public function test_the_raw_supplier_is_never_serialised_on_an_order(): void
    {
        $user = User::factory()->create();
        $sms = SmsOrder::create([
            'user_id' => $user->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp',
            'type' => 'otp', 'phone_number' => '+15550001111', 'status' => 'waiting',
            'provider_cost' => 0.20, 'charged_to_user' => 0.50, 'profit' => 0.30,
        ]);
        $esim = EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'status' => 'processing',
            'price_charged' => 10, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);

        $this->assertArrayNotHasKey('provider', $sms->fresh()->toArray());
        $this->assertArrayNotHasKey('provider', $esim->fresh()->toArray());
        // Cost/profit stay hidden too (existing money-safety).
        $this->assertArrayNotHasKey('provider_cost', $sms->toArray());
        $this->assertArrayNotHasKey('wholesale_cost', $esim->toArray());
    }

    public function test_the_model_badge_shows_the_public_model_not_the_supplier(): void
    {
        $otp = Blade::render('<x-model-badge type="otp" provider="fivesim" />');
        $this->assertStringContainsString('Naara Verify', $otp);
        $this->assertStringNotContainsString('fivesim', $otp);
        $this->assertStringNotContainsString('5sim', $otp);

        $esim = Blade::render('<x-model-badge :esim="true" />');
        $this->assertStringContainsString('Naara Data', $esim);
        $this->assertStringNotContainsString('esimgo', $esim);
    }
}
