<?php

namespace Tests\Feature;

use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 21-EXT §1 — the seeded four-tier plan catalog. Confirms the exact
 * prices/tiers the owner specified, and that Extended/Extended V2 resolve to
 * the identical entitlement, differing only in support_level.
 */
class WhiteLabelLicensePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_exactly_the_four_specified_plans(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);

        $this->assertSame(4, WhiteLabelLicensePlan::count());

        $basic = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();
        $this->assertSame('1500.00', (string) $basic->price_usd);
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $basic->tier);
        $this->assertStringEndsWith('/images/white-label/plan-basic.webp', $basic->cover_image_url);

        $medium = WhiteLabelLicensePlan::where('key', 'medium')->firstOrFail();
        $this->assertSame('2500.00', (string) $medium->price_usd);
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $medium->tier);

        $extended = WhiteLabelLicensePlan::where('key', 'extended')->firstOrFail();
        $this->assertSame('5000.00', (string) $extended->price_usd);
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $extended->tier);
        $this->assertSame(WhiteLabelLicensePlan::SUPPORT_STANDARD, $extended->support_level);

        $extendedV2 = WhiteLabelLicensePlan::where('key', 'extended_v2')->firstOrFail();
        $this->assertSame('7500.00', (string) $extendedV2->price_usd);
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $extendedV2->tier);
        $this->assertSame(WhiteLabelLicensePlan::SUPPORT_PRIORITY, $extendedV2->support_level);

        // Extended and Extended V2 are the SAME feature tier — only support differs.
        $this->assertSame($extended->tier, $extendedV2->tier);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_plans(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);

        $this->assertSame(4, WhiteLabelLicensePlan::count());
    }

    public function test_balance_to_extended_is_computed_live_from_the_active_extended_price(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);

        $basic = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();
        $medium = WhiteLabelLicensePlan::where('key', 'medium')->firstOrFail();
        $extended = WhiteLabelLicensePlan::where('key', 'extended')->firstOrFail();

        $this->assertSame(3500.0, WhiteLabelLicensePlan::balanceToExtended($basic));
        $this->assertSame(2500.0, WhiteLabelLicensePlan::balanceToExtended($medium));
        // Already at/above Extended — nothing to top up.
        $this->assertNull(WhiteLabelLicensePlan::balanceToExtended($extended));
    }

    public function test_balance_to_extended_reflects_a_later_price_change_not_a_hardcoded_figure(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);

        WhiteLabelLicensePlan::where('key', 'extended')->update(['price_usd' => 6000.00]);
        $basic = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        $this->assertSame(4500.0, WhiteLabelLicensePlan::balanceToExtended($basic));
    }

    public function test_a_white_label_instance_can_reference_a_plan_and_sum_its_own_payments(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->firstOrFail();

        $instance = WhiteLabelInstance::create([
            'brand_name' => 'Test Brand', 'slug' => 'test-brand', 'contact_email' => 'a@b.co',
            'status' => WhiteLabelInstance::PENDING, 'license_plan_id' => $plan->id,
        ]);

        $this->assertTrue($instance->licensePlan->is($plan));
        $this->assertSame(0.0, $instance->amountPaidTotal());

        $instance->payments()->create(['amount_usd' => 1500.00, 'kind' => 'initial', 'payment_reference' => 'ref-1']);
        $this->assertSame(1500.0, $instance->fresh()->amountPaidTotal());

        $instance->payments()->create(['amount_usd' => 3500.00, 'kind' => 'balance_completion', 'payment_reference' => 'ref-2']);
        $this->assertSame(5000.0, $instance->fresh()->amountPaidTotal());
    }
}
