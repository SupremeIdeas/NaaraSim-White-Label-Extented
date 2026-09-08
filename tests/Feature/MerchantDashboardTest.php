<?php

namespace Tests\Feature;

use App\Livewire\MerchantDashboard;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantEarningsService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 3.5 — the merchant storefront dashboard. Active merchants only;
 * they brand the storefront, share their invite, and cash out earnings — but
 * never touch pricing.
 */
class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function activeMerchant(): Merchant
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('merchant');

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Sahara Connect',
            'slug' => 'sahara', 'brand_color' => '#0A6E6E', 'status' => Merchant::ACTIVE,
        ]);
    }

    public function test_only_an_active_merchant_can_open_the_dashboard(): void
    {
        // A plain user: 404.
        Livewire::actingAs(User::factory()->create())->test(MerchantDashboard::class)->assertStatus(404);

        // A suspended merchant: 404.
        $m = $this->activeMerchant();
        $m->update(['status' => Merchant::SUSPENDED]);
        Livewire::actingAs($m->owner)->test(MerchantDashboard::class)->assertStatus(404);
    }

    public function test_the_dashboard_shows_earnings_and_the_invite_link(): void
    {
        $m = $this->activeMerchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 10.0, 15.0, 'earn:seed'); // +5

        Livewire::actingAs($m->owner)->test(MerchantDashboard::class)
            ->assertOk()
            ->assertSee('Sahara Connect')
            ->assertSee('5.00')          // available balance
            ->assertSee('/merchant/sahara/join'); // invite link
    }

    public function test_a_merchant_can_rebrand_their_storefront_but_not_pricing(): void
    {
        Storage::fake('public');
        $m = $this->activeMerchant();

        Livewire::actingAs($m->owner)->test(MerchantDashboard::class)
            ->set('businessName', 'Sahara Global')
            ->set('brandColor', '#123456')
            ->call('saveStorefront')
            ->assertHasNoErrors();

        $m->refresh();
        $this->assertSame('Sahara Global', $m->business_name);
        $this->assertSame('#123456', $m->brand_color);
    }

    public function test_a_merchant_can_withdraw_available_earnings(): void
    {
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $m = $this->activeMerchant();
        $customer = User::factory()->create(['merchant_id' => $m->id]);
        app(MerchantEarningsService::class)->accrue($m, $customer, 'esim', 10.0, 30.0, 'earn:seed'); // +20
        PayoutAccount::create([
            'user_id' => $m->owner_user_id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'SAHARA LTD',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($m->owner)->test(MerchantDashboard::class)
            ->set('amountUsd', 10)
            ->call('withdraw')
            ->assertHasNoErrors();

        $this->assertSame(10.0, app(MerchantEarningsService::class)->balance($m)); // 20 − 10 held
        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $m->owner_user_id, 'source_bucket' => 'merchant_earnings',
        ]);
    }
}
