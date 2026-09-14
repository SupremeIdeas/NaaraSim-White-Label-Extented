<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Platform\PlatformWithdrawalService;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prompt 21-EXT §5.3/§5.4 — the platform-earnings cash-out. Reuses
 * PayoutService/PayoutThreshold exactly like MerchantWithdrawalService does,
 * with no owning merchant: a super_admin withdraws the ONE global bucket to
 * THEIR OWN verified account, and a reversed transfer returns the hold to
 * that same global bucket (ReturnPlatformEarnings).
 */
class PlatformWithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function account(User $user): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'Supreme Ideas',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);
    }

    /** Accrue real platform earnings the same way a license sale would. */
    private function seedEarnings(float $amount): void
    {
        $payer = User::factory()->create();
        app(WalletService::class)->credit($payer, $amount, 'USD');
        $instance = app(WhiteLabelLicenseService::class)->register(['brand_name' => 'X', 'contact_email' => 'x@x.test']);
        $instance->forceFill(['price_usd' => $amount, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();
        app(WhiteLabelLicenseService::class)->payAndActivate($instance, $payer);
    }

    public function test_a_super_admin_can_withdraw_which_holds_the_bucket(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $this->seedEarnings(1500);
        $admin = $this->superAdmin();
        $account = $this->account($admin);

        $request = app(PlatformWithdrawalService::class)->request($admin, $account, 1000.0);

        $this->assertSame('platform_earnings', $request->source_bucket);
        $this->assertSame(500.0, app(PlatformEarningsService::class)->balance()); // held
    }

    public function test_a_non_super_admin_cannot_withdraw_platform_earnings(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $this->seedEarnings(1500);
        $notAdmin = User::factory()->create();
        $account = $this->account($notAdmin);

        $this->expectException(PayoutException::class);
        app(PlatformWithdrawalService::class)->request($notAdmin, $account, 500.0);
    }

    public function test_withdrawing_more_than_the_balance_is_rejected(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $this->seedEarnings(1500);
        $admin = $this->superAdmin();
        $account = $this->account($admin);

        $this->expectException(PayoutException::class);
        app(PlatformWithdrawalService::class)->request($admin, $account, 5000.0);
    }

    public function test_an_account_belonging_to_another_user_is_refused(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $this->seedEarnings(1500);
        $admin = $this->superAdmin();
        $someoneElse = User::factory()->create();
        $account = $this->account($someoneElse);

        $this->expectException(PayoutException::class);
        app(PlatformWithdrawalService::class)->request($admin, $account, 500.0);
    }

    public function test_a_failed_transfer_returns_the_held_earnings_to_the_global_bucket(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $gateway = new class implements PayoutGatewayInterface
        {
            public function name(): string { return 'paystack'; }

            public function available(): bool { return true; }

            public function createRecipient(PayoutAccount $account): string { return 'RCP'; }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                return new PayoutTransferResult(status: 'failed', failureReason: 'Bank rejected');
            }

            public function verifyWebhook(\Illuminate\Http\Request $request): bool { return true; }

            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payouts\PayoutEvent { return null; }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gateway]));

        $this->seedEarnings(1500);
        $admin = $this->superAdmin();
        $account = $this->account($admin);

        $request = app(PlatformWithdrawalService::class)->request($admin, $account, 1000.0);
        $this->assertSame(500.0, app(PlatformEarningsService::class)->balance()); // held

        app(PayoutService::class)->send($request); // fails → PayoutReversed → earnings returned

        $this->assertSame(PayoutRequest::FAILED, $request->fresh()->status);
        $this->assertSame(1500.0, app(PlatformEarningsService::class)->balance()); // returned
    }
}
