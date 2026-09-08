<?php

namespace Tests\Feature;

use App\Livewire\Withdraw;
use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payouts\BankResolverInterface;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\ResolvedAccount;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 1 — the customer cash-out page. Browsing and payout-account
 * setup are free (NAARA-BUILD-22 §3); KYC-L2 is only required once the
 * unified free-payout threshold is spent. Manages payout accounts (name
 * resolved before saving) and withdraws withdrawable credits.
 */
class WithdrawPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
    }

    private function fakeResolver(): void
    {
        $resolver = new class implements BankResolverInterface
        {
            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function supports(string $country): bool
            {
                return $country === 'NG';
            }

            public function banks(string $country): array
            {
                return [['code' => '058', 'name' => 'GTBank']];
            }

            public function resolve(string $country, string $bankCode, string $accountNumber): ?ResolvedAccount
            {
                return $accountNumber === '0123456789'
                    ? new ResolvedAccount(accountName: 'JANE TRAVELLER', provider: 'paystack')
                    : null;
            }
        };
        $this->app->instance(PayoutAccountService::class, new PayoutAccountService([$resolver]));
    }

    private function verifiedUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        KycVerification::create([
            'user_id' => $user->id, 'level' => 2, 'provider' => 'manual',
            'status' => KycVerification::APPROVED, 'reference' => 'kyc:'.$user->id,
        ]);

        return $user;
    }

    public function test_unverified_users_can_open_the_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/rewards/withdraw')->assertOk()->assertSee('Withdraw earnings');
    }

    public function test_an_unverified_user_can_add_a_payout_account(): void
    {
        $this->fakeResolver();
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('country', 'NG')->set('bankCode', '058')->set('accountNumber', '0123456789')
            ->call('addAccount')
            ->assertSet('accountError', null);

        $this->assertDatabaseHas('payout_accounts', ['user_id' => $user->id, 'account_name' => 'JANE TRAVELLER']);
    }

    public function test_an_unverified_user_can_withdraw_within_the_free_threshold(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        app(CreditService::class)->rewardReferral($user, 1, 1000); // $10 withdrawable
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', null);

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $user->id, 'source_bucket' => 'referral_credits', 'status' => 'pending',
        ]);
    }

    public function test_an_unverified_user_past_the_free_threshold_is_blocked(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        app(CreditService::class)->rewardReferral($user, 1, 1000);
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);
        // Burn the default 5 free payouts (any bucket counts, per the unified
        // threshold).
        foreach (range(1, 5) as $i) {
            PayoutRequest::create([
                'user_id' => $user->id, 'amount' => 1, 'currency' => 'USD',
                'source_bucket' => 'referral_earnings', 'status' => PayoutRequest::PAID,
                'provider' => 'paystack', 'reference' => 'burn'.$i,
            ]);
        }

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', fn ($v) => str_contains($v, 'free payout limit'));

        $this->assertDatabaseCount('payout_requests', 5);
    }

    public function test_verified_users_can_open_the_page(): void
    {
        $this->actingAs($this->verifiedUser())->get('/rewards/withdraw')->assertOk()->assertSee('Withdraw earnings');
    }

    public function test_adding_an_account_resolves_and_saves_it(): void
    {
        $this->fakeResolver();
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('country', 'NG')->set('bankCode', '058')->set('accountNumber', '0123456789')
            ->call('addAccount')
            ->assertSet('accountError', null);

        $this->assertDatabaseHas('payout_accounts', ['user_id' => $user->id, 'account_name' => 'JANE TRAVELLER']);
    }

    public function test_a_bad_account_number_shows_an_error(): void
    {
        $this->fakeResolver();
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('country', 'NG')->set('bankCode', '058')->set('accountNumber', '0000000000')
            ->call('addAccount')
            ->assertSet('accountError', fn ($v) => $v !== null);

        $this->assertDatabaseCount('payout_accounts', 0);
    }

    public function test_a_verified_user_can_request_a_withdrawal(): void
    {
        $user = $this->verifiedUser();
        app(CreditService::class)->rewardReferral($user, 1, 1000); // $10 withdrawable
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', null);

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $user->id, 'source_bucket' => 'referral_credits', 'status' => 'pending',
        ]);
    }

    public function test_paypal_toggle_only_shows_when_paypal_is_configured(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->get('/rewards/withdraw')->assertDontSee('PayPal');

        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $this->actingAs($user)->get('/rewards/withdraw')->assertSee('PayPal');
    }

    public function test_a_matching_paypal_email_pair_saves_a_verified_account(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountType', 'paypal')
            ->set('paypalEmail', 'jane@example.com')
            ->set('paypalEmailConfirm', 'jane@example.com')
            ->call('addPaypalAccount')
            ->assertSet('accountError', null);

        $this->assertDatabaseHas('payout_accounts', [
            'user_id' => $user->id, 'type' => 'paypal', 'provider' => 'paypal',
            'account_number' => 'jane@example.com', 'is_verified' => true,
        ]);
    }

    public function test_mismatched_paypal_emails_are_rejected(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountType', 'paypal')
            ->set('paypalEmail', 'jane@example.com')
            ->set('paypalEmailConfirm', 'typo@example.com')
            ->call('addPaypalAccount')
            ->assertSet('accountError', fn ($v) => $v !== null);

        $this->assertDatabaseCount('payout_accounts', 0);
    }

    public function test_stripe_tab_only_shows_when_stripe_is_configured(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->get('/rewards/withdraw')->assertDontSee('Stripe');

        config(['services.stripe.secret_key' => 'sk_test_stripe']);
        $this->actingAs($user)->get('/rewards/withdraw')->assertSee('Stripe');
    }

    public function test_connect_stripe_creates_an_account_and_redirects_to_onboarding(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_stripe']);
        Http::fake([
            'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_new']),
            'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.com/setup/1']),
        ]);
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountType', 'stripe')
            ->call('connectStripe')
            ->assertRedirect('https://connect.stripe.com/setup/1');

        $this->assertDatabaseHas('payout_accounts', [
            'user_id' => $user->id, 'type' => 'stripe', 'account_number' => 'acct_new', 'is_verified' => false,
        ]);
    }

    public function test_an_account_still_onboarding_shows_a_continue_setup_prompt(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_stripe']);
        Http::fake(['api.stripe.com/v1/accounts/acct_pending' => Http::response([
            'id' => 'acct_pending', 'details_submitted' => false, 'charges_enabled' => false, 'payouts_enabled' => false,
        ])]);
        $user = $this->verifiedUser();
        PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_pending',
            'account_name' => 'acct_pending', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);

        $this->actingAs($user)->get('/rewards/withdraw')->assertOk()->assertSee('Onboarding incomplete');
    }

    public function test_withdrawing_to_a_not_yet_enabled_stripe_account_is_refused(): void
    {
        // mount() force-refreshes any pending Stripe account — keep it pending.
        Http::fake(['api.stripe.com/v1/accounts/acct_pending' => Http::response([
            'id' => 'acct_pending', 'details_submitted' => false, 'charges_enabled' => false, 'payouts_enabled' => false,
        ])]);
        $user = $this->verifiedUser();
        app(CreditService::class)->rewardReferral($user, 1, 1000);
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_pending',
            'account_name' => 'acct_pending', 'provider' => 'stripe', 'is_verified' => false, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', fn ($v) => $v !== null);

        $this->assertDatabaseCount('payout_requests', 0);
    }

    public function test_withdrawing_to_an_enabled_stripe_account_succeeds(): void
    {
        $user = $this->verifiedUser();
        app(CreditService::class)->rewardReferral($user, 1, 1000);
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'stripe', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => 'stripe', 'bank_name' => 'Stripe', 'account_number' => 'acct_ready',
            'account_name' => 'acct_ready', 'provider' => 'stripe', 'is_verified' => true,
            'payouts_enabled' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', null);

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $user->id, 'provider' => 'stripe', 'status' => 'pending',
        ]);
    }
}
