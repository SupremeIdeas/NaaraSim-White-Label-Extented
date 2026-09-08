<?php

namespace Tests\Feature;

use App\Livewire\Admin\Credits as AdminCredits;
use App\Livewire\Rewards;
use App\Models\AdRewardView;
use App\Models\CreditLedger;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Support\CreditSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** NaaraCredits loyalty + rewards. */
class NaaraCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        CreditSettings::flush();
    }

    private function credits(): CreditService
    {
        return app(CreditService::class);
    }

    public function test_earn_and_spend_are_atomic_idempotent_and_ledgered(): void
    {
        $user = User::factory()->create()->fresh();
        $c = $this->credits();

        $c->earn($user, 100, 'admin', 'grant-1', 'Test grant');
        $c->earn($user, 100, 'admin', 'grant-1'); // idempotent replay — no double credit
        $this->assertSame(100.0, $c->balance($user->fresh()));
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->count());

        $c->spend($user->fresh(), 40, 'redeem', 'spend-1');
        $this->assertSame(60.0, $c->balance($user->fresh()));

        $this->expectException(InsufficientCreditsException::class);
        $c->spend($user->fresh(), 1000, 'redeem', 'spend-2');
    }

    public function test_conversion_uses_the_admin_rate(): void
    {
        Setting::setValue('credits.per_usd', 200, 'credits');
        CreditSettings::flush();
        $this->assertSame(2.0, CreditSettings::creditsToUsd(400));
        $this->assertSame(400.0, CreditSettings::usdToCredits(2));
    }

    public function test_daily_checkin_grants_once_per_cooldown(): void
    {
        Setting::setValue('credits.checkin_daily', 5, 'credits');
        CreditSettings::flush();
        $user = User::factory()->create()->fresh();
        \App\Models\UserWallet::create(['user_id' => $user->id]);

        $this->assertTrue($this->credits()->canCheckIn($user->fresh()));
        $this->assertSame(5.0, $this->credits()->checkIn($user->fresh()));
        // Immediately again → on cooldown, no grant.
        $this->assertSame(0.0, $this->credits()->checkIn($user->fresh()));
        $this->assertSame(5.0, $this->credits()->balance($user->fresh()));
    }

    public function test_grant_once_is_idempotent_for_signup_and_first_purchase(): void
    {
        $user = User::factory()->create()->fresh();
        $this->credits()->grantOnce($user, 50, 'first_purchase', 'First purchase bonus');
        $this->credits()->grantOnce($user->fresh(), 50, 'first_purchase', 'First purchase bonus');
        $this->assertSame(50.0, $this->credits()->balance($user->fresh()));
    }

    public function test_signup_grants_the_configured_bonus(): void
    {
        Setting::setValue('credits.signup_bonus', 25, 'credits');
        CreditSettings::flush();

        $this->post('/register', [
            'name' => 'New User', 'email' => 'new@naarasim.test',
            'password' => 'password12', 'password_confirmation' => 'password12',
        ]);

        $user = User::where('email', 'new@naarasim.test')->firstOrFail();
        $this->assertSame(25.0, $this->credits()->balance($user));
    }

    // ---- rewarded-ad postback ----------------------------------------------

    private function enableAds(string $secret = 'sekret'): void
    {
        config(['services.offerwall.postback_secret' => $secret]);
        Setting::setValue('credits.ads_enabled', true, 'credits');
        Setting::setValue('credits.ad_offerwall_url', 'https://wall.example/offers', 'credits');
        Setting::setValue('credits.ad_daily_cap', 100, 'credits');
        CreditSettings::flush();
    }

    public function test_postback_credits_only_with_a_valid_signature_and_is_idempotent(): void
    {
        $this->enableAds();
        $user = User::factory()->create()->fresh();
        \App\Models\UserWallet::create(['user_id' => $user->id]);

        $amount = 10;
        $txn = 'tx-123';
        $sig = hash_hmac('sha256', "{$user->id}:{$amount}:{$txn}", 'sekret');

        // Bad signature → rejected, no credit.
        $this->post('/webhooks/offerwall', ['user' => $user->id, 'amount' => $amount, 'txn' => $txn, 'signature' => 'wrong'])
            ->assertStatus(401);
        $this->assertSame(0.0, $this->credits()->balance($user->fresh()));

        // Valid signature → credited.
        $this->post('/webhooks/offerwall', ['user' => $user->id, 'amount' => $amount, 'txn' => $txn, 'signature' => $sig])
            ->assertOk();
        $this->assertSame(10.0, $this->credits()->balance($user->fresh()));

        // Replay of the same txn → no double credit.
        $this->post('/webhooks/offerwall', ['user' => $user->id, 'amount' => $amount, 'txn' => $txn, 'signature' => $sig])
            ->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(10.0, $this->credits()->balance($user->fresh()));
        $this->assertSame(1, AdRewardView::where('status', 'credited')->count());
    }

    public function test_postback_enforces_the_daily_cap(): void
    {
        $this->enableAds();
        Setting::setValue('credits.ad_daily_cap', 15, 'credits');
        CreditSettings::flush();
        $user = User::factory()->create()->fresh();
        \App\Models\UserWallet::create(['user_id' => $user->id]);

        foreach ([['t1', 10], ['t2', 10]] as [$txn, $amt]) {
            $sig = hash_hmac('sha256', "{$user->id}:{$amt}:{$txn}", 'sekret');
            $this->post('/webhooks/offerwall', ['user' => $user->id, 'amount' => $amt, 'txn' => $txn, 'signature' => $sig]);
        }
        // First 10 credited, second would exceed the 15 cap → rejected.
        $this->assertSame(10.0, $this->credits()->balance($user->fresh()));
        $this->assertSame(1, AdRewardView::where('status', 'rejected')->count());
    }

    public function test_postback_is_disabled_when_ads_off(): void
    {
        config(['services.offerwall.postback_secret' => 'sekret']);
        // ads_enabled stays false
        $this->post('/webhooks/offerwall', ['user' => 1, 'amount' => 5, 'txn' => 't', 'signature' => 'x'])
            ->assertStatus(403);
    }

    // ---- pages --------------------------------------------------------------

    public function test_rewards_page_shows_balance_and_lets_a_user_check_in(): void
    {
        Setting::setValue('credits.checkin_daily', 5, 'credits');
        CreditSettings::flush();
        $user = User::factory()->create()->fresh();

        Livewire::actingAs($user)->test(Rewards::class)
            ->assertSee('NaaraCredits')
            ->assertSee('Daily check-in')
            ->call('checkIn')
            ->assertSee('You earned');

        $this->assertSame(5.0, $this->credits()->balance($user->fresh()));
    }

    public function test_admin_credits_page_saves_and_is_admin_only(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(AdminCredits::class)
            ->set('per_usd', 150)
            ->set('checkin_daily', 3)
            ->set('max_redeem_pct', 40)
            ->call('save')
            ->assertHasNoErrors();

        CreditSettings::flush();
        $this->assertSame(150, CreditSettings::perUsd());

        $user = User::factory()->create()->fresh();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/credits')->assertNotFound();
    }
}
