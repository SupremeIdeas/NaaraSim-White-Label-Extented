<?php

namespace Tests\Feature;

use App\Livewire\BrandManage;
use App\Livewire\GetListed;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\BrandHandleFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** BUILD-9 §5.1/§5.5/§9: get-listed, guided onboarding + management. */
class BrandOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $handles = 1, int $videos = 0): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => 19, 'handles_included' => $handles, 'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => $videos, 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_choosing_a_plan_subscribes_and_redirects_to_management(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 30, 'USD');
        $plan = $this->plan();

        Livewire::actingAs($user)->test(GetListed::class)
            ->call('choose', $plan->id)
            ->assertRedirect(route('brand.manage'));

        $this->assertDatabaseHas('brand_partners', ['owner_user_id' => $user->id, 'listing_status' => BrandPartner::STATUS_PENDING]);
    }

    public function test_completing_setup_activates_the_listing(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 30, 'USD');
        $plan = $this->plan();
        $brand = BrandPartner::create(['owner_user_id' => $user->id, 'brand_name' => 'Old', 'listing_status' => BrandPartner::STATUS_PENDING, 'background_color' => '#0A6E6E', 'current_plan_id' => $plan->id, 'is_active' => true]);
        \App\Models\BrandSubscription::create(['brand_partner_id' => $brand->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now(), 'next_billing_at' => now()->addMonth()]);
        BrandPartnerHandle::create(['brand_partner_id' => $brand->id, 'platform' => 'x', 'handle_label' => 'H', 'handle_url' => 'https://x.com/h', 'credit_reward' => 2, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);
        $brand->fallback_image = '/img/x.webp';
        $brand->save();

        Livewire::actingAs($user)->test(BrandManage::class)
            ->set('profile.brand_name', 'Acme')
            ->set('profile.category', 'Tech & Apps')
            ->set('profile.background_color', '#123456')
            ->call('saveProfile');

        $this->assertSame(BrandPartner::STATUS_ACTIVE, $brand->fresh()->listing_status);
    }

    public function test_handle_cap_and_url_format_are_enforced(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan(handles: 1);
        $brand = BrandPartner::create(['owner_user_id' => $user->id, 'brand_name' => 'Acme', 'listing_status' => BrandPartner::STATUS_PENDING, 'background_color' => '#0A6E6E', 'current_plan_id' => $plan->id, 'is_active' => true]);

        // Bad URL format is rejected.
        Livewire::actingAs($user)->test(BrandManage::class)
            ->set('newHandle.platform', 'instagram')
            ->set('newHandle.handle_label', 'IG')
            ->set('newHandle.handle_url', 'https://example.com/notinsta')
            ->call('addHandle')
            ->assertHasErrors('newHandle.handle_url');
        $this->assertSame(0, $brand->handles()->count());

        // Valid one is accepted; a second exceeds the 1-handle plan cap.
        Livewire::actingAs($user)->test(BrandManage::class)
            ->set('newHandle.platform', 'instagram')->set('newHandle.handle_label', 'IG')->set('newHandle.handle_url', 'https://instagram.com/acme')->call('addHandle')
            ->set('newHandle.platform', 'x')->set('newHandle.handle_label', 'X')->set('newHandle.handle_url', 'https://x.com/acme')->call('addHandle');
        $this->assertSame(1, $brand->handles()->count());

        $this->assertTrue(BrandHandleFormat::matches('youtube', 'https://youtube.com/@naara'));
        $this->assertFalse(BrandHandleFormat::matches('youtube', 'https://youtube.com/naara'));
    }
}
