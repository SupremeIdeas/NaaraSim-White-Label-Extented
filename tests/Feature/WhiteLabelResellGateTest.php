<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 21-EXT §6 — resell-status gating. Two Setting-backed flags default
 * open; a closed tier is hard-rejected server-side (never trusting the
 * carousel alone hid it); and each threshold auto-closes from a plain count
 * of already-sold WhiteLabelInstance rows the moment a self-service sale
 * actually lands — never a shadow counter column.
 */
class WhiteLabelResellGateTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WhiteLabelLicenseService
    {
        return app(WhiteLabelLicenseService::class);
    }

    private function fundedUser(float $usd): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD');

        return $user;
    }

    /** Bulk-insert already-sold self-service rows without going through the
     *  full payment flow — only their count matters to the gate. */
    private function seedSoldInstances(int $count, string $tier): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'brand_name' => 'Bulk '.$tier.' '.$i,
                'slug' => 'bulk-'.$tier.'-'.$i,
                'contact_email' => 'bulk-'.$tier.'-'.$i.'@test.co',
                'status' => WhiteLabelInstance::ACTIVE,
                'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
                'tier' => $tier,
                'license_issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        WhiteLabelInstance::insert($rows);
    }

    public function test_both_flags_default_open_when_never_set(): void
    {
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_EXTENDED));
    }

    public function test_register_is_hard_rejected_server_side_when_the_requested_tiers_flag_is_closed(): void
    {
        Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);

        $this->expectException(\RuntimeException::class);
        $this->service()->register([
            'brand_name' => 'Blocked', 'contact_email' => 'blk@co.test',
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
    }

    public function test_register_still_succeeds_for_an_open_tier_while_the_other_is_closed(): void
    {
        Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);

        $instance = $this->service()->register([
            'brand_name' => 'Still Open', 'contact_email' => 'open@co.test',
            'requested_tier' => WhiteLabelInstance::TIER_EXTENDED,
        ]);

        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
    }

    public function test_a_request_with_no_requested_tier_is_never_gated(): void
    {
        Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
        Setting::setValue(WhiteLabelLicensePlan::SETTING_EXTENDED_OPEN, false);

        // Admin-provisioned registrations (Prompt 21 pre-dates the plan/tier
        // gate entirely) never set requested_tier and must be unaffected.
        $instance = $this->service()->register(['brand_name' => 'Admin Flow', 'contact_email' => 'adm@co.test']);
        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
    }

    public function test_normal_tier_auto_closes_the_instant_the_200th_self_service_sale_lands(): void
    {
        $this->seedSoldInstances(199, WhiteLabelInstance::TIER_NORMAL);
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));

        $payer = $this->fundedUser(1500);
        $instance = $this->service()->register([
            'brand_name' => 'The 200th', 'contact_email' => 'two-hundred@co.test',
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();

        $this->service()->payAndActivate($instance, $payer);

        $this->assertFalse(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_EXTENDED), 'extended must stay untouched by the normal threshold');
    }

    public function test_extended_tier_auto_closes_both_flags_at_the_2000th_sale(): void
    {
        $this->seedSoldInstances(1999, WhiteLabelInstance::TIER_EXTENDED);
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_EXTENDED));

        $payer = $this->fundedUser(5000);
        $instance = $this->service()->register([
            'brand_name' => 'The 2000th', 'contact_email' => 'two-thousand@co.test',
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_EXTENDED,
        ]);
        $instance->forceFill(['price_usd' => 5000.00])->save();

        $this->service()->payAndActivate($instance, $payer);

        $this->assertFalse(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_EXTENDED));
        $this->assertFalse(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL), 'crossing 2000 extended sales closes everything');
    }

    public function test_an_admin_can_manually_reopen_a_threshold_closed_tier(): void
    {
        Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
        $this->assertFalse(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));

        Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, true);
        $this->assertTrue(WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL));
    }
}
