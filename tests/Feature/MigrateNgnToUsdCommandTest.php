<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Services\Pricing\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * wallet:migrate-ngn-to-usd (Unified USD Wallet, Part B §3.3 Migration 2) —
 * the one-time backfill converting legacy ngn_balance into the spendable
 * usd_balance at the live rate.
 */
class MigrateNgnToUsdCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the rate so the expected USD figure is deterministic:
        // NGN 4500 / 1500 = USD 3.00.
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();
    }

    public function test_it_converts_legacy_ngn_into_usd_and_zeroes_it(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id, 'ngn_balance' => 4500, 'usd_balance' => 2]);

        $this->artisan('wallet:migrate-ngn-to-usd')->assertSuccessful();

        $wallet = $user->wallet->fresh();
        $this->assertSame('0.00', (string) $wallet->ngn_balance);
        $this->assertSame('5.0000', (string) $wallet->usd_balance); // 2 existing + 3 converted

        $debit = WalletTransaction::where('reference', "ngn-to-usd-migration:{$user->id}:ngn")->first();
        $credit = WalletTransaction::where('reference', "ngn-to-usd-migration:{$user->id}:usd")->first();
        $this->assertSame('debit', $debit->type);
        $this->assertSame('4500.0000', (string) $debit->amount);
        $this->assertSame('NGN', $debit->currency);
        $this->assertSame('credit', $credit->type);
        $this->assertSame('3.0000', (string) $credit->amount);
        $this->assertSame('USD', $credit->currency);
        $this->assertSame('NGN', $credit->paid_currency);
    }

    public function test_it_never_inflates_lifetime_deposit_or_spend_totals(): void
    {
        $user = User::factory()->create();
        UserWallet::create([
            'user_id' => $user->id, 'ngn_balance' => 4500, 'usd_balance' => 0,
            'total_deposits' => 12.5, 'total_spent' => 4,
        ]);

        $this->artisan('wallet:migrate-ngn-to-usd');

        $wallet = $user->wallet->fresh();
        $this->assertSame('12.50', (string) $wallet->total_deposits);
        $this->assertSame('4.00', (string) $wallet->total_spent);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id, 'ngn_balance' => 4500, 'usd_balance' => 0]);

        $this->artisan('wallet:migrate-ngn-to-usd', ['--dry-run' => true])->assertSuccessful();

        $wallet = $user->wallet->fresh();
        $this->assertSame('4500.00', (string) $wallet->ngn_balance);
        $this->assertSame('0.0000', (string) $wallet->usd_balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_a_wallet_with_no_legacy_balance_is_skipped(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id, 'ngn_balance' => 0, 'usd_balance' => 10]);

        $this->artisan('wallet:migrate-ngn-to-usd')->assertSuccessful();

        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_running_it_twice_does_not_double_convert(): void
    {
        $user = User::factory()->create();
        UserWallet::create(['user_id' => $user->id, 'ngn_balance' => 4500, 'usd_balance' => 0]);

        $this->artisan('wallet:migrate-ngn-to-usd');
        $this->artisan('wallet:migrate-ngn-to-usd');

        $wallet = $user->wallet->fresh();
        $this->assertSame('0.00', (string) $wallet->ngn_balance);
        $this->assertSame('3.0000', (string) $wallet->usd_balance);
        $this->assertSame(2, WalletTransaction::count());
    }
}
