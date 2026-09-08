<?php

namespace Tests\Feature;

use App\Livewire\MerchantJoin;
use App\Models\Merchant;
use App\Models\User;
use App\Support\MerchantBranding;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 3.3 — co-branding + invite links. A merchant's
 * /merchant/{slug}/join captures the invite; a signup through it permanently
 * links the new customer to that merchant. NaaraSim branding is never replaced.
 */
class MerchantInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function merchant(string $status = Merchant::ACTIVE): Merchant
    {
        return Merchant::create([
            'owner_user_id' => User::factory()->create()->id,
            'business_name' => 'Sahara Connect', 'slug' => 'sahara-connect',
            'brand_color' => '#0A6E6E', 'status' => $status,
        ]);
    }

    public function test_the_join_page_shows_an_active_merchant_and_captures_the_invite(): void
    {
        $merchant = $this->merchant();

        Livewire::test(MerchantJoin::class, ['slug' => $merchant->slug])
            ->assertOk()
            ->assertSee('Sahara Connect')
            ->assertSee('Powered by');

        $this->assertSame($merchant->slug, session(MerchantBranding::SESSION_KEY));
    }

    public function test_the_join_page_404s_for_an_inactive_or_unknown_merchant(): void
    {
        $suspended = $this->merchant(Merchant::SUSPENDED);

        Livewire::test(MerchantJoin::class, ['slug' => $suspended->slug])->assertStatus(404);
        Livewire::test(MerchantJoin::class, ['slug' => 'nope'])->assertStatus(404);
    }

    public function test_registering_through_an_invite_links_the_customer_to_the_merchant(): void
    {
        $merchant = $this->merchant();
        session([MerchantBranding::SESSION_KEY => $merchant->slug]);

        $this->post('/register', [
            'name' => 'Ada', 'email' => 'ada@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($merchant->id, $user->merchant_id);
        // Invite consumed — not reused for the next signup.
        $this->assertNull(session(MerchantBranding::SESSION_KEY));
    }

    public function test_a_signup_with_no_invite_has_no_merchant(): void
    {
        $this->post('/register', [
            'name' => 'Ben', 'email' => 'ben@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ]);

        $this->assertNull(User::where('email', 'ben@example.com')->first()->merchant_id);
    }

    public function test_co_branding_follows_an_active_merchant_only(): void
    {
        $merchant = $this->merchant();
        $customer = User::factory()->create(['merchant_id' => $merchant->id]);

        $this->assertSame($merchant->id, MerchantBranding::forCustomer($customer)->id);

        // Suspend the merchant → co-branding stops.
        $merchant->update(['status' => Merchant::SUSPENDED]);
        $this->assertNull(MerchantBranding::forCustomer($customer->fresh()));
    }
}
