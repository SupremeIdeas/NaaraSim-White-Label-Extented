<?php

namespace Tests\Feature;

use App\Livewire\MerchantWhiteLabel;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 21-EXT §2/§3 — the merchant-facing self-service license flow. A
 * non-merchant is 404'd (same as every other merchant screen); a Standard
 * merchant sees the page but it's locked (unlike MerchantClients' 404 — this
 * gate is visible-but-locked, mirroring the base Merchant-V2 gate); a V2
 * merchant can request a plan, pay to activate, and pay the remaining
 * balance to upgrade to Extended without disturbing their live token.
 */
class MerchantWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
    }

    private function merchant(string $tier = Merchant::TIER_STANDARD, float $fund = 0): Merchant
    {
        $owner = User::factory()->create();
        if ($fund > 0) {
            app(WalletService::class)->credit($owner, $fund, 'USD');
        }

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => Merchant::ACTIVE, 'tier' => $tier,
        ]);
    }

    public function test_a_non_merchant_gets_a_404(): void
    {
        Livewire::actingAs(User::factory()->create())->test(MerchantWhiteLabel::class)->assertStatus(404);
    }

    public function test_a_standard_merchant_sees_the_page_locked_not_404(): void
    {
        $merchant = $this->merchant(Merchant::TIER_STANDARD);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertOk()
            ->assertSet('isV2', false)
            ->assertSee('Merchant V2 required');
    }

    public function test_a_v2_merchant_sees_the_plan_catalog(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertOk()
            ->assertSet('isV2', true)
            ->assertSee('Basic')
            ->assertSee('Extended V2');
    }

    public function test_the_comparison_table_shows_every_plan_and_its_price(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSee('Full platform, unlocked day one')
            ->assertSee('Pay balance later to reach Extended')
            ->assertSee('$1,500')
            ->assertSee('$7,500')
            ->assertSee('Priority');
    }

    public function test_a_v2_merchant_can_request_a_plan(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('disclaimerAcknowledged', true)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', null);

        $instance = WhiteLabelInstance::where('merchant_id', $merchant->id)->first();
        $this->assertNotNull($instance);
        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
        $this->assertSame($plan->id, $instance->license_plan_id);
        $this->assertSame(WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE, $instance->acquisition_method);
    }

    public function test_requesting_without_acknowledging_the_disclaimer_is_refused(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', fn ($e) => $e !== null);

        $this->assertNull(WhiteLabelInstance::where('merchant_id', $merchant->id)->first());
    }

    public function test_a_standard_merchant_cannot_request_a_plan_via_the_component(): void
    {
        $merchant = $this->merchant(Merchant::TIER_STANDARD);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('disclaimerAcknowledged', true)
            ->call('requestLicense', $plan->id)
            ->assertSet('error', fn ($e) => $e !== null);

        $this->assertNull(WhiteLabelInstance::where('merchant_id', $merchant->id)->first());
    }

    public function test_a_priced_request_can_be_paid_from_the_dashboard(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 1500);
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->call('payNow')
            ->assertSet('error', null);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->fresh()->status);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
    }

    public function test_an_active_normal_instance_can_pay_the_balance_to_upgrade(): void
    {
        $merchant = $this->merchant(Merchant::TIER_V2, fund: 5000);
        $basic = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id, 'license_plan_id' => $basic->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();
        app(WhiteLabelLicenseService::class)->payAndActivate($instance->fresh(), $merchant->owner);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->assertSet('balanceOwed', 3500.0)
            ->call('payBalance')
            ->assertSet('error', null);

        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->fresh()->tier);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
    }
}
