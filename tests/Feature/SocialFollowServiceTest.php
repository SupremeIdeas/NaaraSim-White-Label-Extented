<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\SocialFollowClaim;
use App\Models\SocialFollowHandle;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Social\SocialFollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-6 §C.3: the follow claim is money-safe — a surprise credit granted
 * server-side, exactly once per user per handle, via CreditService (no second
 * ledger), and unrepeatable.
 */
class SocialFollowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function handle(float $reward = 5.0, bool $active = true): SocialFollowHandle
    {
        return SocialFollowHandle::create([
            'platform' => 'instagram', 'handle_label' => 'Naara Nigeria',
            'handle_url' => 'https://instagram.com/naara', 'credit_reward' => $reward,
            'verification' => 'self', 'is_active' => $active, 'sort_order' => 1,
        ]);
    }

    public function test_a_follow_grants_the_surprise_credit_once_and_records_the_claim(): void
    {
        $user = User::factory()->create();
        $handle = $this->handle(7.5);
        $svc = app(SocialFollowService::class);

        $result = $svc->claim($user, $handle);

        $this->assertFalse($result['already']);
        $this->assertSame(7.5, $result['earned']);
        $this->assertSame(7.5, app(CreditService::class)->balance($user->fresh()));
        $this->assertDatabaseHas('social_follow_claims', ['user_id' => $user->id, 'handle_id' => $handle->id]);
        $this->assertTrue($svc->hasClaimed($user, $handle));
    }

    public function test_claiming_the_same_handle_again_never_double_grants(): void
    {
        $user = User::factory()->create();
        $handle = $this->handle(5.0);
        $svc = app(SocialFollowService::class);

        $svc->claim($user, $handle);
        $second = $svc->claim($user, $handle);

        $this->assertTrue($second['already']);
        $this->assertSame(0.0, $second['earned']);
        $this->assertSame(5.0, app(CreditService::class)->balance($user->fresh())); // still just one grant
        $this->assertSame(1, SocialFollowClaim::where('user_id', $user->id)->count());
    }

    public function test_an_inactive_handle_grants_nothing(): void
    {
        $user = User::factory()->create();
        $handle = $this->handle(9.0, active: false);
        $svc = app(SocialFollowService::class);

        $result = $svc->claim($user, $handle);

        $this->assertSame(0.0, $result['earned']);
        $this->assertSame(0.0, app(CreditService::class)->balance($user->fresh()));
        $this->assertFalse($svc->hasClaimed($user, $handle));
    }

    public function test_brand_partner_handles_claim_through_the_same_table_independently(): void
    {
        $user = User::factory()->create();
        $svc = app(SocialFollowService::class);

        $platformHandle = $this->handle(4.0);
        $brand = BrandPartner::create(['brand_name' => 'Acme', 'background_color' => '#123456', 'sort_order' => 1, 'is_active' => true]);
        $brandHandle = BrandPartnerHandle::create([
            'brand_partner_id' => $brand->id, 'platform' => 'x', 'handle_label' => 'Acme HQ',
            'handle_url' => 'https://x.com/acme', 'credit_reward' => 6.0, 'verification' => 'self',
            'sort_order' => 1, 'is_active' => true,
        ]);

        $svc->claim($user, $platformHandle);
        $brandResult = $svc->claim($user, $brandHandle);

        $this->assertSame(6.0, $brandResult['earned']);
        $this->assertSame(10.0, app(CreditService::class)->balance($user->fresh())); // 4 + 6
        $this->assertDatabaseHas('social_follow_claims', ['user_id' => $user->id, 'brand_partner_handle_id' => $brandHandle->id]);
        $this->assertTrue($svc->hasClaimed($user, $brandHandle));
        // The two claim kinds don't collide.
        $this->assertSame(2, SocialFollowClaim::where('user_id', $user->id)->count());
    }
}
