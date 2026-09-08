<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\BankResolverInterface;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\ResolvedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ROADMAP §Layer 0.1 — payout accounts. The account name is resolved from the
 * PSP before anything is saved (money never goes to a typo); an unconfirmable
 * account is refused; exactly one default is kept per user.
 */
class PayoutAccountTest extends TestCase
{
    use RefreshDatabase;

    /** A resolver stub: confirms a fixed name for one "good" account number. */
    private function resolver(bool $available = true, ?string $name = 'JANE A. TRAVELLER'): BankResolverInterface
    {
        return new class($available, $name) implements BankResolverInterface
        {
            public function __construct(private bool $available, private ?string $name)
            {
            }

            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return $this->available;
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
                if ($accountNumber !== '0123456789' || $this->name === null) {
                    return null;
                }

                return new ResolvedAccount(accountName: $this->name, provider: 'paystack');
            }
        };
    }

    private function service(?BankResolverInterface $resolver = null): PayoutAccountService
    {
        return new PayoutAccountService([$resolver ?? $this->resolver()]);
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058',
            'account_number' => '0123456789',
        ], $overrides);
    }

    public function test_it_resolves_the_name_and_saves_a_verified_account(): void
    {
        $user = User::factory()->create();

        $account = $this->service()->addAccount($user, $this->data());

        $this->assertSame('JANE A. TRAVELLER', $account->account_name);
        $this->assertTrue($account->is_verified);
        $this->assertTrue($account->is_default); // first account becomes default
        $this->assertDatabaseHas('payout_accounts', ['user_id' => $user->id, 'provider' => 'paystack']);
    }

    public function test_it_refuses_to_save_an_unconfirmable_account(): void
    {
        $user = User::factory()->create();

        $this->expectException(AccountResolutionException::class);
        $this->service()->addAccount($user, $this->data(['account_number' => '9999999999']));

        $this->assertDatabaseCount('payout_accounts', 0);
    }

    public function test_an_unsupported_country_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(AccountResolutionException::class);
        $this->service()->addAccount($user, $this->data(['country' => 'US']));
    }

    public function test_a_resolver_without_a_key_does_not_participate(): void
    {
        $user = User::factory()->create();

        $this->expectException(AccountResolutionException::class);
        $this->service($this->resolver(available: false))->addAccount($user, $this->data());
    }

    public function test_adding_a_second_default_unsets_the_first(): void
    {
        $user = User::factory()->create();
        $service = $this->service();

        $first = $service->addAccount($user, $this->data());
        $second = $service->addAccount($user, $this->data(['make_default' => true]));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, PayoutAccount::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_removing_the_default_promotes_another_account(): void
    {
        $user = User::factory()->create();
        $service = $this->service();

        $first = $service->addAccount($user, $this->data());
        $second = $service->addAccount($user, $this->data());
        $this->assertTrue($first->fresh()->is_default);

        $service->remove($user, $first->fresh());

        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_the_account_number_is_masked_for_display(): void
    {
        $user = User::factory()->create();
        $account = $this->service()->addAccount($user, $this->data());

        $this->assertStringEndsWith('6789', $account->masked_number);
        $this->assertStringNotContainsString('012345', $account->masked_number);
    }
}
