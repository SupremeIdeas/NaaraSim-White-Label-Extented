<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Jobs\CreditWalletJob;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * CreditWalletJob edge cases (security audit — money-safety).
 *
 * A verified payment must NEVER crash-loop the queue. The wallet only holds
 * USD/NGN, so WalletService throws for any other currency. If a top-up ever
 * reaches the credit job in an uncreditable currency (e.g. a local-currency
 * deposit whose USD-locking intent failed to persist), the job must swallow the
 * exception, alert an admin for manual reconciliation, and stop — not retry
 * forever nor double-charge on a later replay.
 */
class CreditWalletJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_usd_topup_credits_the_wallet(): void
    {
        $user = User::factory()->create();

        (new CreditWalletJob('stripe', 'ref-ok', $user->id, 10.0, 'USD'))
            ->handle(app(WalletService::class));

        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_an_uncreditable_currency_alerts_instead_of_crash_looping(): void
    {
        Bus::fake([AlertAdminJob::class]);
        $user = User::factory()->create();

        // Unified USD Wallet (Part B): every currency CurrencyService models
        // (GHS included) now converts cleanly to USD via creditTopUp(). Only a
        // currency CurrencyService doesn't recognise at all should ever hit
        // this uncreditable path — e.g. a corrupted/unknown code slipping
        // through raw. The job must not throw.
        (new CreditWalletJob('paystack', 'ref-xyz', $user->id, 50.0, 'XYZ'))
            ->handle(app(WalletService::class));

        // No money moved, and an admin alert was queued for manual reconciliation.
        $this->assertSame(0, WalletTransaction::where('user_id', $user->id)->count());
        Bus::assertDispatched(AlertAdminJob::class, fn ($job) => $job->code === 'topup_uncreditable_currency');
    }

    public function test_a_missing_user_is_a_no_op(): void
    {
        // A verified webhook for a since-deleted user must not throw.
        (new CreditWalletJob('stripe', 'ref-gone', 999999, 10.0, 'USD'))
            ->handle(app(WalletService::class));

        $this->assertSame(0, WalletTransaction::count());
    }
}
