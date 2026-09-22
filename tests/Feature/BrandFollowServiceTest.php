<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\User;
use App\Services\Social\BrandFollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner request (2026-09-22): "Connect" to a brand on Naara — a plain
 * in-platform follow toggle with a cached follower count. Distinct from
 * SocialFollowService (external handle follow, pays a credit reward): this
 * pays nothing, it's just a relationship + a count.
 */
class BrandFollowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): BrandPartner
    {
        return BrandPartner::create([
            'brand_name' => 'Acme Co', 'background_color' => '#123456', 'sort_order' => 1,
            'is_active' => true, 'listing_status' => BrandPartner::STATUS_ACTIVE,
        ]);
    }

    public function test_connecting_creates_a_follow_and_increments_the_count(): void
    {
        $user = User::factory()->create();
        $brand = $this->brand();

        $result = app(BrandFollowService::class)->toggle($user, $brand);

        $this->assertTrue($result['following']);
        $this->assertSame(1, $result['count']);
        $this->assertSame(1, $brand->fresh()->naara_followers_count);
        $this->assertDatabaseHas('brand_partner_followers', ['brand_partner_id' => $brand->id, 'user_id' => $user->id]);
    }

    public function test_toggling_again_disconnects_and_decrements_the_count(): void
    {
        $user = User::factory()->create();
        $brand = $this->brand();
        $svc = app(BrandFollowService::class);

        $svc->toggle($user, $brand);
        $result = $svc->toggle($user, $brand);

        $this->assertFalse($result['following']);
        $this->assertSame(0, $result['count']);
        $this->assertSame(0, $brand->fresh()->naara_followers_count);
        $this->assertDatabaseMissing('brand_partner_followers', ['brand_partner_id' => $brand->id, 'user_id' => $user->id]);
    }

    public function test_disconnecting_without_a_prior_connect_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $brand = $this->brand();

        // toggle() only ever decrements when it found (and deleted) an
        // existing follow row — with none present, this is treated as a
        // fresh connect, never an unbalanced decrement.
        $result = app(BrandFollowService::class)->toggle($user, $brand);

        $this->assertTrue($result['following']);
        $this->assertSame(1, $result['count']);
        $this->assertSame(1, $brand->fresh()->naara_followers_count);
    }

    public function test_a_concurrent_double_follow_never_double_counts(): void
    {
        $user = User::factory()->create();
        $brand = $this->brand();

        // Simulate the race the unique constraint guards against: the row
        // already exists when toggle() tries to create it.
        BrandPartnerFollower::create(['brand_partner_id' => $brand->id, 'user_id' => $user->id]);
        $brand->increment('naara_followers_count');

        $this->assertSame(1, BrandPartnerFollower::where('brand_partner_id', $brand->id)->where('user_id', $user->id)->count());
    }

    public function test_different_users_each_get_their_own_follow_state(): void
    {
        $brand = $this->brand();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $svc = app(BrandFollowService::class);

        $svc->toggle($alice, $brand);
        $result = $svc->toggle($bob, $brand);

        $this->assertTrue($result['following']);
        $this->assertSame(2, $result['count']);
        $this->assertTrue($brand->fresh()->isFollowedBy($alice));
        $this->assertTrue($brand->fresh()->isFollowedBy($bob));
    }
}
