<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wallet USD reservation (earmark) primitive — the money core of Merchant V2
 * auto-renewal. Reserved funds stay in the wallet but are removed from spendable
 * and can NEVER be spent by an ordinary debit until released.
 */
class WalletReservationTest extends TestCase
{
    use RefreshDatabase;

    private function funded(float $usd): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user;
    }

    public function test_reserve_reduces_spendable_but_not_balance(): void
    {
        $user = $this->funded(100);
        $svc = app(WalletService::class);

        $svc->reserve($user, 30);

        $this->assertSame('100.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(30.0, $svc->reservedUsd($user->fresh()));
        $this->assertSame(70.0, $svc->spendableUsd($user->fresh()));
    }

    public function test_a_debit_cannot_touch_reserved_funds(): void
    {
        $user = $this->funded(100);
        $svc = app(WalletService::class);
        $svc->reserve($user, 60);

        // 50 > spendable(40) → refused even though balance is 100.
        $this->expectException(InsufficientBalanceException::class);
        $svc->debit($user, 50, 'USD', ['reference' => 'buy:1']);
    }

    public function test_a_debit_can_spend_exactly_the_spendable(): void
    {
        $user = $this->funded(100);
        $svc = app(WalletService::class);
        $svc->reserve($user, 60);

        $svc->debit($user, 40, 'USD', ['reference' => 'buy:2']); // == spendable
        $this->assertSame('60.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0.0, $svc->spendableUsd($user->fresh()));
    }

    public function test_release_returns_funds_to_spendable(): void
    {
        $user = $this->funded(100);
        $svc = app(WalletService::class);
        $svc->reserve($user, 60);
        $svc->release($user, 60);

        $this->assertSame(0.0, $svc->reservedUsd($user->fresh()));
        $this->assertSame(100.0, $svc->spendableUsd($user->fresh()));
        // And a full-balance debit now succeeds.
        $svc->debit($user, 100, 'USD', ['reference' => 'buy:3']);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_cannot_reserve_more_than_spendable(): void
    {
        $user = $this->funded(50);
        $svc = app(WalletService::class);
        $svc->reserve($user, 40);

        $this->expectException(InsufficientBalanceException::class);
        $svc->reserve($user, 20); // only 10 spendable left
    }

    public function test_release_never_goes_negative(): void
    {
        $user = $this->funded(50);
        $svc = app(WalletService::class);
        $svc->reserve($user, 10);
        $svc->release($user, 999); // over-release is clamped

        $this->assertSame(0.0, $svc->reservedUsd($user->fresh()));
    }
}
