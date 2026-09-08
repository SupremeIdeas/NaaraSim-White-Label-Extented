<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Support\BrandCategories;
use Database\Seeders\BrandPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BUILD-9 §2 + §7: the self-service brand schema, models and taxonomy. */
class BrandDirectorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_plans_seed_and_expose_cost_per_follower(): void
    {
        $this->seed(BrandPlanSeeder::class);
        $this->assertSame(3, BrandSubscriptionPlan::count());
        $growth = BrandSubscriptionPlan::where('name', 'Growth')->first();
        // 49 / (150 * 3 handles) = 0.109
        $this->assertEqualsWithDelta(0.109, $growth->costPerGuaranteedFollower(), 0.001);
    }

    public function test_a_self_service_brand_links_owner_plan_and_subscription(): void
    {
        $this->seed(BrandPlanSeeder::class);
        $owner = User::factory()->create();
        $plan = BrandSubscriptionPlan::first();

        $brand = BrandPartner::create([
            'owner_user_id' => $owner->id, 'brand_name' => 'Acme', 'category' => 'Tech & Apps',
            'listing_status' => BrandPartner::STATUS_PENDING, 'is_featured' => false,
            'background_color' => '#123456', 'current_plan_id' => $plan->id, 'is_active' => true,
        ]);
        BrandSubscription::create([
            'brand_partner_id' => $brand->id, 'plan_id' => $plan->id, 'status' => BrandSubscription::ACTIVE,
            'started_at' => now(), 'next_billing_at' => now()->addMonth(),
        ]);

        $this->assertTrue($brand->isSelfService());
        $this->assertSame($owner->id, $brand->owner->id);
        $this->assertSame($plan->id, $brand->plan->id);
        $this->assertSame(BrandSubscription::ACTIVE, $brand->subscription->status);
    }

    public function test_directory_scope_shows_only_active_listings_featured_first(): void
    {
        $featured = BrandPartner::create(['brand_name' => 'Featured', 'is_featured' => true, 'priority_score' => 0, 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#000', 'is_active' => true]);
        $boosted = BrandPartner::create(['brand_name' => 'Boosted', 'is_featured' => false, 'priority_score' => 50, 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#000', 'is_active' => true]);
        $plain = BrandPartner::create(['brand_name' => 'Plain', 'is_featured' => false, 'priority_score' => 0, 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#000', 'is_active' => true]);
        BrandPartner::create(['brand_name' => 'Hidden', 'listing_status' => BrandPartner::STATUS_PAUSED, 'background_color' => '#000', 'is_active' => true]);

        $listed = BrandPartner::listed()->directoryOrder()->pluck('brand_name')->all();
        $this->assertSame(['Featured', 'Boosted', 'Plain'], $listed); // paused one excluded
    }

    public function test_taxonomy_always_includes_other_last(): void
    {
        $all = BrandCategories::all();
        $this->assertContains('Tech & Apps', $all);
        $this->assertSame('Other', end($all));
        $this->assertTrue(BrandCategories::isValid('Music & Artists'));
    }
}
