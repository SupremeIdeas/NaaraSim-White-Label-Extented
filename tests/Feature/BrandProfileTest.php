<?php

namespace Tests\Feature;

use App\Livewire\BrandProfile;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request (2026-09-22): a dedicated per-brand profile page reachable
 * from the Brand Hunt directory. Same visibility and tier-gate rules as the
 * directory itself, and the same server-granted, one-time follow reward.
 */
class BrandProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    private function listedBrand(): BrandPartner
    {
        return BrandPartner::create([
            'brand_name' => 'Acme Co', 'background_color' => '#123456', 'sort_order' => 1,
            'is_active' => true, 'listing_status' => BrandPartner::STATUS_ACTIVE,
        ]);
    }

    public function test_the_profile_page_renders_for_a_listed_brand(): void
    {
        $brand = $this->listedBrand();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->assertStatus(200)
            ->assertSee('Acme Co');
    }

    public function test_a_paused_listing_404s_exactly_like_its_absent_from_the_directory(): void
    {
        $brand = BrandPartner::create([
            'brand_name' => 'Paused Co', 'background_color' => '#123456', 'sort_order' => 1,
            'is_active' => true, 'listing_status' => BrandPartner::STATUS_PENDING,
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->assertStatus(404);
    }

    public function test_an_inactive_brand_404s(): void
    {
        $brand = BrandPartner::create([
            'brand_name' => 'Inactive Co', 'background_color' => '#123456', 'sort_order' => 1,
            'is_active' => false, 'listing_status' => BrandPartner::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->assertStatus(404);
    }

    /**
     * White Label EXTENDED variant (this repo — see FeatureGateTest): Extended
     * ships with ZERO feature locks, so the profile page stays reachable even
     * with a lock list stored, unlike a normal white-label fork which would
     * 404 here.
     */
    public function test_the_profile_page_stays_reachable_on_extended_even_when_a_lock_is_stored(): void
    {
        $brand = $this->listedBrand();
        $user = User::factory()->create();
        config()->set('updater.product_identifier', 'naarasim-whitelabel-extended');
        FeatureEntitlements::store([FeatureLocks::F_BRAND_HUNT]);

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->assertStatus(200);
    }

    public function test_following_a_brand_handle_from_the_profile_page_grants_the_reward_once(): void
    {
        $brand = $this->listedBrand();
        $handle = BrandPartnerHandle::create([
            'brand_partner_id' => $brand->id, 'platform' => 'x', 'handle_label' => 'Acme HQ',
            'handle_url' => 'https://x.com/acme', 'credit_reward' => 6.0, 'verification' => 'self',
            'sort_order' => 1, 'is_active' => true,
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->call('followBrandHandle', $handle->id);

        $this->assertSame(6.0, app(CreditService::class)->balance($user->fresh()));

        // A second claim never double-grants.
        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->call('followBrandHandle', $handle->id);
        $this->assertSame(6.0, app(CreditService::class)->balance($user->fresh()));
    }

    public function test_a_handle_belonging_to_a_different_brand_cannot_be_claimed_through_this_profile(): void
    {
        $brand = $this->listedBrand();
        $otherBrand = BrandPartner::create([
            'brand_name' => 'Other Co', 'background_color' => '#654321', 'sort_order' => 2,
            'is_active' => true, 'listing_status' => BrandPartner::STATUS_ACTIVE,
        ]);
        $otherHandle = BrandPartnerHandle::create([
            'brand_partner_id' => $otherBrand->id, 'platform' => 'x', 'handle_label' => 'Other HQ',
            'handle_url' => 'https://x.com/other', 'credit_reward' => 9.0, 'verification' => 'self',
            'sort_order' => 1, 'is_active' => true,
        ]);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->call('followBrandHandle', $otherHandle->id);

        $this->assertSame(0.0, app(CreditService::class)->balance($user->fresh()));
    }

    /** Owner request (2026-09-22): "Connect" toggles state and updates the follower count, no reward involved. */
    public function test_connecting_from_the_profile_page_toggles_state_and_count(): void
    {
        $brand = $this->listedBrand();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->call('toggleConnect')
            ->assertSet('brandPartner.naara_followers_count', 1)
            ->assertSee('Connected');

        $this->assertTrue($brand->fresh()->isFollowedBy($user));
        $this->assertSame(0.0, app(CreditService::class)->balance($user->fresh())); // no credit for connecting

        Livewire::actingAs($user)->test(BrandProfile::class, ['brandPartner' => $brand])
            ->call('toggleConnect')
            ->assertSet('brandPartner.naara_followers_count', 0);

        $this->assertFalse($brand->fresh()->isFollowedBy($user));
    }
}
