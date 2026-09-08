<?php

namespace Tests\Feature;

use App\Livewire\Admin\Banners as AdminBanners;
use App\Livewire\Admin\Coupons as AdminCoupons;
use App\Livewire\Checkout;
use App\Livewire\GetNumber;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\User;
use App\Services\Pricing\CouponEngine;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * Module 31 — promo banners + margin-protected coupons.
 * The core money invariant under test: NO coupon can ever push a charged
 * price to or below provider cost + minimum profit.
 */
class CouponsAndBannersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        $this->seed(RoleSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
        \App\Support\Banners::flush();
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo',
            'provider_plan_id' => 'cpn-1',
            'name' => 'USA 3GB 30D',
            'data_mb' => 3072,
            'validity_days' => 30,
            'countries' => ['US'],
            'cost_price_usd' => 3.77,
            'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    private function coupon(array $extra = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE20',
            'percent_off' => 20,
            'applies_to' => 'all',
            'per_user_limit' => 1,
            'is_active' => true,
        ], $extra));
    }

    // ---------------------------------------------------------------- engine

    public function test_coupon_discount_is_clamped_above_cost_plus_minimum_profit(): void
    {
        \App\Models\Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        \App\Models\Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');
        $engine = app(CouponEngine::class);
        $coupon = $this->coupon(['code' => 'MEGA90', 'percent_off' => 90]);

        // 90% off $10 asks for $1.00 — but the margin-safe floor now binds:
        // $10/$8 → $2 margin, 30% cap → floor = 8 + 2*0.70 = 9.40.
        $quote = $engine->price($coupon, 10.00, 8.00, 'esim');
        $this->assertSame(9.40, $quote['price']);
        $this->assertTrue($quote['clamped']);
        $this->assertSame(0.60, $quote['saved']);

        // The clamp is auditable (same margin_guard floor, marked as a coupon).
        $this->assertDatabaseHas('pricing_engine_logs', [
            'provider' => 'coupon:MEGA90',
            'guard_active' => 'margin_guard',
        ]);

        // A high-margin plan is now protected too: $10/$0.50 → $9.50 margin,
        // 30% cap → floor 0.50 + 9.50*0.70 = 7.15 (the OLD flat floor let this
        // reach $1.00 — exactly the erosion this blueprint closes).
        $quote = $engine->price($coupon, 10.00, 0.50, 'esim');
        $this->assertSame(7.15, round($quote['price'], 2));
        $this->assertTrue($quote['price'] > 0.50); // never at/below cost
    }

    public function test_usable_rejects_expired_inactive_exhausted_and_offproduct_codes(): void
    {
        $engine = app(CouponEngine::class);
        $user = User::factory()->create();

        $this->assertNull($engine->usable('NOPE', $user, 'esim')); // unknown

        $expired = $this->coupon(['code' => 'OLD', 'expires_at' => now()->subHour()]);
        $this->assertNull($engine->usable('OLD', $user, 'esim'));

        $paused = $this->coupon(['code' => 'PAUSED', 'is_active' => false]);
        $this->assertNull($engine->usable('PAUSED', $user, 'esim'));

        $spent = $this->coupon(['code' => 'GONE', 'max_redemptions' => 5, 'times_redeemed' => 5]);
        $this->assertNull($engine->usable('GONE', $user, 'esim'));

        $esimOnly = $this->coupon(['code' => 'ESIMONLY', 'applies_to' => 'esim']);
        $this->assertNull($engine->usable('ESIMONLY', $user, 'number'));
        $this->assertNotNull($engine->usable('esimonly', $user, 'esim')); // case-insensitive

        // Per-user limit: one use each by default.
        $live = $this->coupon(['code' => 'ONCE']);
        $engine->redeem($live, $user, 'esim', 'ref-1', 10, 8, false);
        $this->assertNull($engine->usable('ONCE', $user, 'esim'));
        $this->assertNotNull($engine->usable('ONCE', User::factory()->create(), 'esim'));
    }

    // ------------------------------------------------------------- checkout

    public function test_checkout_charges_the_discounted_retail_and_records_the_redemption(): void
    {
        $plan = $this->plan();
        $this->coupon(); // SAVE20
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: [
            'iccid' => '8944000', 'orderReference' => 'ORD-1',
        ]));

        \App\Models\Setting::setValue('pricing.minimum_profit_usd', 0.50, 'pricing');
        \App\Models\Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');

        // Margin-safe floor: $10/$3.77 → $6.23 margin, 30% cap → max discount
        // $1.869, so a 20% ($2) coupon is clamped to a $8.131 charge.
        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->set('coupon', 'save20')
            ->call('applyCoupon')
            ->assertSet('couponPrice', 8.131)
            ->assertSet('couponSaved', 1.869)
            ->assertDontSee('3.77')           // cost still never rendered
            ->call('purchase')
            ->assertSet('done', true);

        $this->assertSame('11.8690', (string) $user->wallet->fresh()->usd_balance);
        $this->assertDatabaseHas('esim_orders', ['user_id' => $user->id, 'price_charged' => 8.131]);
        $redemption = CouponRedemption::firstOrFail();
        $this->assertSame(1.869, (float) $redemption->amount_saved);
        $this->assertTrue($redemption->floor_clamped); // margin floor bound the discount
        $this->assertSame(1, Coupon::where('code', 'SAVE20')->first()->times_redeemed);
    }

    public function test_checkout_rejects_an_invalid_code_without_charging(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('coupon', 'BOGUS')
            ->call('applyCoupon')
            ->assertSet('couponPrice', null)
            ->assertSet('couponError', 'That coupon code is not valid for this purchase.')
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', false);

        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0, CouponRedemption::count());
    }

    public function test_number_order_applies_the_coupon_to_the_live_quote_with_the_floor(): void
    {
        // Cost 0.20 -> retail 0.31 -> $0.11 margin. A 90% coupon asks for ~0.03,
        // but the margin-safe floor (30% of margin) binds: 0.20 + 0.11*0.70 =
        // 0.277 — well above the absolute cost + 0.01 = 0.21 floor.
        \App\Models\Setting::setValue('pricing.discount_margin_cap_pct', 30, 'pricing');
        \App\Models\Setting::setValue('pricing.sms_min_profit', 0.01, 'pricing');
        $this->coupon(['code' => 'NUM95', 'percent_off' => 90, 'applies_to' => 'number']);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('number.fivesim', new FakeSmsProvider(
            price: 0.20,
            buyResponse: ['provider_ref' => '5S-1', 'number' => '2348010000000', 'cost' => 0.20, 'status' => 'pending'],
        ));
        \Illuminate\Support\Facades\Queue::fake();

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')
            ->set('service', 'whatsapp')
            ->set('coupon', 'NUM95')
            ->call('order')
            ->assertSet('error', null);

        $redemption = CouponRedemption::firstOrFail();
        $this->assertTrue($redemption->floor_clamped);
        $this->assertSame(0.277, round((float) $redemption->paid_price, 3)); // margin floor, never below cost+profit
        $this->assertGreaterThanOrEqual(0.21, (float) $redemption->paid_price); // absolute cost+profit floor still holds
        $this->assertSame(round(20 - 0.277, 2), round((float) $user->wallet->fresh()->usd_balance, 2));
    }

    // ---------------------------------------------------------------- admin

    public function test_admin_creates_coupons_and_used_coupons_are_paused_not_deleted(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(AdminCoupons::class)
            ->set('code', 'JULY10')
            ->set('percent_off', 10)
            ->set('applies_to', 'all')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'JULY10', 'is_active' => true]);

        // Over-cap percent is rejected at the form level.
        Livewire::actingAs($admin)->test(AdminCoupons::class)
            ->set('code', 'TOOMUCH')->set('percent_off', 95)
            ->call('save')->assertHasErrors(['percent_off']);

        // A used coupon can't be deleted — it gets paused (audit trail).
        $coupon = Coupon::where('code', 'JULY10')->first();
        app(CouponEngine::class)->redeem($coupon, $admin, 'esim', 'r', 10, 9, false);
        Livewire::actingAs($admin)->test(AdminCoupons::class)->call('delete', $coupon->id);
        $this->assertDatabaseHas('coupons', ['code' => 'JULY10', 'is_active' => false]);

        // An unused one deletes cleanly.
        $fresh = $this->coupon(['code' => 'UNUSED']);
        Livewire::actingAs($admin)->test(AdminCoupons::class)->call('delete', $fresh->id);
        $this->assertDatabaseMissing('coupons', ['code' => 'UNUSED']);
    }

    public function test_admin_uploads_banners_jpg_or_webp_only_and_pages_are_admin_only(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(AdminBanners::class)
            ->set('title', 'July sale')
            ->set('placement', 'dashboard_home')
            ->set('image', UploadedFile::fake()->image('promo.jpg', 1200, 400))
            ->set('link_url', '/catalogue')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('banners', ['title' => 'July sale', 'placement' => 'dashboard_home']);

        // PNG artwork is rejected — banners are JPG/WebP by spec.
        Livewire::actingAs($admin)->test(AdminBanners::class)
            ->set('title', 'Bad type')
            ->set('placement', 'menu_sheet')
            ->set('image', UploadedFile::fake()->image('promo.png', 800, 400))
            ->call('save')
            ->assertHasErrors(['image']);

        // javascript: links can never be saved.
        Livewire::actingAs($admin)->test(AdminBanners::class)
            ->set('title', 'Evil')
            ->set('placement', 'menu_sheet')
            ->set('image', UploadedFile::fake()->image('x.jpg'))
            ->set('link_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['link_url']);

        // Both pages 404 for normal users.
        $user = User::factory()->create()->fresh();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/banners')->assertNotFound();
        $this->actingAs($user)->get('/adminmaster/coupons')->assertNotFound();
    }

    // ----------------------------------------------------------- user zones

    public function test_live_banners_render_in_their_zones_and_hidden_ones_do_not(): void
    {
        $live = Banner::create([
            'title' => 'Data sale',
            'placement' => 'dashboard_home',
            'image_url' => 'https://cdn.test/banners/live.webp',
            'link_url' => '/catalogue',
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Paused promo',
            'placement' => 'dashboard_home',
            'image_url' => 'https://cdn.test/banners/paused.webp',
            'is_active' => false,
        ]);
        Banner::create([
            'title' => 'Future promo',
            'placement' => 'dashboard_home',
            'image_url' => 'https://cdn.test/banners/future.webp',
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);
        Banner::create([
            'title' => 'Menu promo',
            'placement' => 'menu_sheet',
            'image_url' => 'https://cdn.test/banners/menu.webp',
            'is_active' => true,
            'coupon_id' => $this->coupon(['code' => 'MENU5', 'percent_off' => 5])->id,
        ]);

        $user = User::factory()->create()->fresh();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('https://cdn.test/banners/live.webp')
            ->assertDontSee('paused.webp')
            ->assertDontSee('future.webp')
            // menu_sheet banner renders in the customer shell's More sheet,
            // with its copyable coupon chip.
            ->assertSee('https://cdn.test/banners/menu.webp')
            ->assertSee('MENU5');

        // The account zone renders its own placement.
        Banner::create([
            'title' => 'Account promo',
            'placement' => 'account',
            'image_url' => 'https://cdn.test/banners/account.webp',
            'is_active' => true,
        ]);
        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('https://cdn.test/banners/account.webp');
    }

    public function test_wallet_page_shows_the_spending_card_with_real_sums(): void
    {
        $user = User::factory()->create()->fresh();
        $walletSvc = app(WalletService::class);
        $walletSvc->credit($user, 50, 'USD');
        $walletSvc->debit($user, 12.5, 'USD', ['description' => 'eSIM: Test']);

        $this->actingAs($user)->get('/wallet')
            ->assertOk()
            ->assertSee('My spending')
            ->assertSee('$12.50')            // this-month spend
            ->assertSee('Topped up (USD)')
            ->assertSee('nx-aurora', false)  // aurora card present
            ->assertSee('Quick amounts');    // collapsible top-up card
    }

    public function test_admin_overview_carries_the_revenue_hero_and_donut(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        $plan = $this->plan();
        \App\Models\EsimOrder::create([
            'user_id' => $admin->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 10, 'wholesale_cost' => 3.77, 'currency' => 'USD',
        ]);
        \App\Models\SmsOrder::create([
            'user_id' => $admin->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp',
            'status' => 'completed', 'provider_cost' => 0.2, 'charged_to_user' => 0.28,
        ]);

        $this->actingAs($admin)->get('/adminmaster')
            ->assertOk()
            ->assertSee('Revenue — last 30 days')
            ->assertSee('$10.28')                 // both product lines counted
            ->assertSee('Revenue split (30d)')
            ->assertSee('nx-anim-card', false)    // animated-border hero
            ->assertSee('nx-donut', false)
            ->assertDontSee('3.7700');            // cost never rendered raw
    }
}
