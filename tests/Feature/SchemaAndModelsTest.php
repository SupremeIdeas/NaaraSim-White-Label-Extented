<?php

namespace Tests\Feature;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\VirtualNumber;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaAndModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_retail_usd_is_generated_from_coalesce(): void
    {
        // Only computed set -> final = computed.
        $a = EsimPlan::create([
            'provider' => 'esimgo',
            'provider_plan_id' => 'A1',
            'name' => 'Plan A',
            'cost_price_usd' => 3.0,
            'computed_retail_usd' => 5.5,
        ])->fresh();

        // Manual override set -> final = manual (override wins).
        $b = EsimPlan::create([
            'provider' => 'airalo',
            'provider_plan_id' => 'B1',
            'name' => 'Plan B',
            'cost_price_usd' => 4.0,
            'computed_retail_usd' => 6.0,
            'manual_retail_usd' => 9.25,
        ])->fresh();

        // Neither set -> final = null.
        $c = EsimPlan::create([
            'provider' => 'quibity',
            'provider_plan_id' => 'C1',
            'name' => 'Plan C',
            'cost_price_usd' => 2.0,
        ])->fresh();

        $this->assertEquals(5.5, (float) $a->final_retail_usd);
        $this->assertEquals(9.25, (float) $b->final_retail_usd);
        $this->assertNull($c->final_retail_usd);
    }

    public function test_generated_column_recomputes_when_manual_override_added(): void
    {
        $plan = EsimPlan::create([
            'provider' => 'esimgo',
            'provider_plan_id' => 'UPD',
            'name' => 'Updatable',
            'cost_price_usd' => 3.0,
            'computed_retail_usd' => 5.0,
        ]);
        $this->assertEquals(5.0, (float) $plan->fresh()->final_retail_usd);

        $plan->update(['manual_retail_usd' => 8.0]);
        $this->assertEquals(8.0, (float) $plan->fresh()->final_retail_usd);
    }

    public function test_private_cost_fields_never_appear_in_serialized_output(): void
    {
        $plan = EsimPlan::create([
            'provider' => 'esimgo',
            'provider_plan_id' => 'SEC',
            'name' => 'Secret Cost',
            'cost_price_usd' => 3.1234,
            'airalo_min_price' => 2.5,
            'markup_pct' => 25.0,
            'override_markup_pct' => 30.0,
            'computed_retail_usd' => 5.0,
        ])->fresh(); // reload so the generated final_retail_usd is populated

        foreach ([$plan->toArray(), json_decode($plan->toJson(), true)] as $payload) {
            $this->assertArrayNotHasKey('cost_price_usd', $payload);
            $this->assertArrayNotHasKey('airalo_min_price', $payload);
            $this->assertArrayNotHasKey('markup_pct', $payload);
            $this->assertArrayNotHasKey('override_markup_pct', $payload);
            // Retail is what users see, and it must still be present.
            $this->assertArrayHasKey('final_retail_usd', $payload);
        }

        $user = User::factory()->create();
        $order = EsimOrder::create([
            'user_id' => $user->id, 'provider' => 'esimgo', 'status' => 'active',
            'price_charged' => 10.0, 'wholesale_cost' => 6.0,
        ]);
        $this->assertArrayNotHasKey('wholesale_cost', $order->toArray());

        $sms = SmsOrder::create([
            'user_id' => $user->id, 'provider' => 'fivesim', 'status' => 'completed',
            'provider_cost' => 0.2, 'charged_to_user' => 0.5, 'profit' => 0.3,
        ]);
        $this->assertArrayNotHasKey('provider_cost', $sms->toArray());
        $this->assertArrayNotHasKey('profit', $sms->toArray());

        $vn = VirtualNumber::create([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15551230000',
            'monthly_cost' => 1.0, 'monthly_retail' => 3.0,
        ]);
        $this->assertArrayNotHasKey('monthly_cost', $vn->toArray());
    }

    public function test_wallet_relationships_and_mass_assignment(): void
    {
        $user = User::factory()->create(['referral_code' => 'NAARA123', 'is_active' => true]);

        $wallet = UserWallet::create([
            'user_id' => $user->id,
            'ngn_balance' => 5000.00,
            'usd_balance' => 12.3456,
        ]);

        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'credit', 'amount' => 5000,
            'currency' => 'NGN', 'balance_before' => 0, 'balance_after' => 5000,
        ]);

        $this->assertTrue($user->wallet->is($wallet));
        $this->assertSame('5000.00', (string) $user->wallet->ngn_balance);
        $this->assertCount(1, $user->walletTransactions);
        $this->assertSame('NAARA123', $user->fresh()->referral_code);
    }

    public function test_every_model_has_a_fillable_allowlist(): void
    {
        $models = [
            \App\Models\UserWallet::class,
            \App\Models\WalletTransaction::class,
            \App\Models\EsimPlan::class,
            \App\Models\EsimOrder::class,
            \App\Models\SmsOrder::class,
            \App\Models\VirtualNumber::class,
            \App\Models\Referral::class,
            \App\Models\OrderLog::class,
            \App\Models\PricingEngineLog::class,
            \App\Models\ProviderWalletLog::class,
            \App\Models\Setting::class,
            \App\Models\WebhookLog::class,
            \App\Models\ErrorLog::class,
            \App\Models\AuditLog::class,
        ];

        foreach ($models as $class) {
            $this->assertNotEmpty(
                (new $class)->getFillable(),
                "$class must declare a \$fillable allowlist"
            );
        }
    }

    public function test_setting_value_is_encrypted_at_rest(): void
    {
        $setting = \App\Models\Setting::create([
            'key' => 'pricing.default_markup',
            'value' => ['pct' => 25, 'floor_usd' => 0.5],
            'group' => 'pricing',
        ]);

        // Round-trips as an array through the cast...
        $this->assertSame(25, $setting->fresh()->value['pct']);

        // ...but the raw stored column is ciphertext, not readable JSON.
        $raw = \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'pricing.default_markup')->value('value');
        $this->assertStringNotContainsString('floor_usd', $raw);
    }
}
