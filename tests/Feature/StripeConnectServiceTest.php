<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Payouts\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stripe Connect onboarding (ROADMAP §Layer 0.2). A connected Express account
 * must finish Stripe's own hosted onboarding before it can receive a payout —
 * this service owns creating the account, generating the (always-fresh)
 * onboarding link, and syncing its capability status.
 */
class StripeConnectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret_key' => 'sk_test_stripe']);
    }

    public function test_account_for_creates_an_express_account_once(): void
    {
        Http::fake(['api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new'])]);
        $user = User::factory()->create();

        $account = app(StripeConnectService::class)->accountFor($user);

        $this->assertSame('stripe', $account->type);
        $this->assertSame('acct_new', $account->account_number);
        $this->assertFalse($account->is_verified);
        Http::assertSentCount(1);

        // A second call reuses the existing account — no new Stripe account created.
        $again = app(StripeConnectService::class)->accountFor($user);
        $this->assertTrue($again->is($account));
        Http::assertSentCount(1);
    }

    public function test_onboarding_url_is_always_freshly_generated(): void
    {
        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new']),
            'api.stripe.com/v1/account_links' => Http::sequence()
                ->push(['url' => 'https://connect.stripe.com/setup/1'])
                ->push(['url' => 'https://connect.stripe.com/setup/2']),
        ]);
        $user = User::factory()->create();
        $connect = app(StripeConnectService::class);
        $account = $connect->accountFor($user);

        $first = $connect->onboardingUrl($account, 'https://app/refresh', 'https://app/return');
        $second = $connect->onboardingUrl($account, 'https://app/refresh', 'https://app/return');

        $this->assertNotSame($first, $second);
    }

    public function test_refresh_status_persists_capability_flags_and_mirrors_is_verified(): void
    {
        $user = User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_1',
            'account_name' => 'acct_1', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);
        Http::fake(['api.stripe.com/v1/accounts/acct_1' => Http::response([
            'id' => 'acct_1', 'details_submitted' => true, 'charges_enabled' => true, 'payouts_enabled' => true,
        ])]);

        $refreshed = app(StripeConnectService::class)->refreshStatus($account);

        $this->assertTrue($refreshed->details_submitted);
        $this->assertTrue($refreshed->charges_enabled);
        $this->assertTrue($refreshed->payouts_enabled);
        $this->assertTrue($refreshed->is_verified);
    }

    public function test_an_account_still_onboarding_stays_unverified(): void
    {
        $user = User::factory()->create();
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_2',
            'account_name' => 'acct_2', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);
        Http::fake(['api.stripe.com/v1/accounts/acct_2' => Http::response([
            'id' => 'acct_2', 'details_submitted' => true, 'charges_enabled' => false, 'payouts_enabled' => false,
        ])]);

        $refreshed = app(StripeConnectService::class)->refreshStatus($account);

        $this->assertFalse($refreshed->payouts_enabled);
        $this->assertFalse($refreshed->is_verified);
    }

    public function test_find_by_account_id_scopes_to_stripe_accounts(): void
    {
        $user = User::factory()->create();
        $stripe = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_3',
            'account_name' => 'acct_3', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);

        $found = app(StripeConnectService::class)->findByAccountId('acct_3');
        $this->assertTrue($found->is($stripe));

        $this->assertNull(app(StripeConnectService::class)->findByAccountId('acct_missing'));
    }
}
