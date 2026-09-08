<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Pricing\CurrencyService;
use App\Services\Wallet\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_credit_increases_balance_and_records_before_and_after(): void
    {
        $user = $this->user();

        $txn = $this->wallet->credit($user, 5000, 'NGN', ['description' => 'Top-up']);

        $this->assertSame('credit', $txn->type);
        $this->assertSame('0.0000', (string) $txn->balance_before);
        $this->assertSame('5000.0000', (string) $txn->balance_after);
        $this->assertSame('5000.00', (string) $user->wallet->ngn_balance);
        $this->assertSame('5000.00', (string) $user->wallet->total_deposits);
    }

    public function test_debit_reduces_balance_updates_total_spent_and_logs_transaction(): void
    {
        $user = $this->user();
        $this->wallet->credit($user, 100, 'USD');

        $txn = $this->wallet->debit($user, 30, 'USD', ['description' => 'eSIM purchase']);

        $this->assertSame('debit', $txn->type);
        $this->assertSame('100.0000', (string) $txn->balance_before);
        $this->assertSame('70.0000', (string) $txn->balance_after);
        $this->assertSame('70.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('30.00', (string) $user->wallet->fresh()->total_spent);
    }

    public function test_refund_credits_back_without_growing_total_deposits(): void
    {
        $user = $this->user();
        $this->wallet->credit($user, 100, 'NGN');
        $this->wallet->debit($user, 40, 'NGN');

        $this->wallet->refund($user, 40, 'NGN', ['description' => 'Order failed']);

        $w = $user->wallet->fresh();
        $this->assertSame('100.00', (string) $w->ngn_balance);      // back to full
        $this->assertSame('100.00', (string) $w->total_deposits);   // unchanged by refund
    }

    public function test_debit_beyond_balance_throws_and_leaves_wallet_untouched(): void
    {
        $user = $this->user();
        $this->wallet->credit($user, 25, 'NGN');

        try {
            $this->wallet->debit($user, 26, 'NGN');
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            // expected
        }

        $this->assertSame('25.00', (string) $user->wallet->fresh()->ngn_balance);
        // Only the initial credit exists; the failed debit wrote nothing.
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->count());
    }

    public function test_idempotent_reference_never_moves_money_twice(): void
    {
        $user = $this->user();
        $this->wallet->credit($user, 100, 'NGN');

        $first = $this->wallet->debit($user, 10, 'NGN', ['reference' => 'order-777']);
        $second = $this->wallet->debit($user, 10, 'NGN', ['reference' => 'order-777']);

        $this->assertTrue($first->is($second));
        $this->assertSame('90.00', (string) $user->wallet->fresh()->ngn_balance);
        $this->assertSame(1, WalletTransaction::where('reference', 'order-777')->count());
    }

    public function test_charge_delivers_and_keeps_the_debit_on_success(): void
    {
        $user = $this->user();
        $this->wallet->credit($user, 100, 'NGN');

        $result = $this->wallet->charge($user, 40, 'NGN', fn ($debit) => 'ICCID-123');

        $this->assertSame('ICCID-123', $result);
        $this->assertSame('60.00', (string) $user->wallet->fresh()->ngn_balance);
    }

    public function test_orphan_charge_guard_auto_refunds_when_delivery_fails(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->wallet->credit($user, 100, 'NGN');

        try {
            $this->wallet->charge($user, 40, 'NGN', function () {
                throw new RuntimeException('provider save failed');
            });
            $this->fail('Expected OrphanChargeRefundedException');
        } catch (OrphanChargeRefundedException $e) {
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }

        // Debited then auto-refunded -> net zero, balance whole again.
        $this->assertSame('100.00', (string) $user->wallet->fresh()->ngn_balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->where('type', 'debit')->count());
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->where('type', 'refund')->count());

        Queue::assertPushed(AlertAdminJob::class);
    }

    public function test_no_double_spend_under_repeated_contention(): void
    {
        // Fund exactly 10 debits' worth, then hammer 15 debits at it.
        $user = $this->user();
        $this->wallet->credit($user, 100, 'NGN');

        $ok = 0;
        $rejected = 0;
        for ($i = 0; $i < 15; $i++) {
            try {
                $this->wallet->debit($user, 10, 'NGN', ['reference' => "spend-$i"]);
                $ok++;
            } catch (InsufficientBalanceException $e) {
                $rejected++;
            }
        }

        $this->assertSame(10, $ok, 'exactly the affordable number of debits should succeed');
        $this->assertSame(5, $rejected);
        $this->assertSame('0.00', (string) $user->wallet->fresh()->ngn_balance);
        $this->assertGreaterThanOrEqual(0, (float) $user->wallet->fresh()->ngn_balance);
        $this->assertSame(10, WalletTransaction::where('user_id', $user->id)->where('type', 'debit')->count());
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->wallet->credit($this->user(), 10, 'EUR');
    }

    public function test_credit_top_up_passes_usd_straight_through(): void
    {
        $user = $this->user();

        $txn = $this->wallet->creditTopUp($user, 20, 'USD', ['description' => 'Top-up']);

        $this->assertSame('USD', $txn->currency);
        $this->assertSame('20.0000', (string) $txn->amount);
        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance);
        // A direct USD top-up's "paid in" figure equals what was credited.
        $this->assertSame('20.0000', (string) $txn->paid_amount);
        $this->assertSame('USD', $txn->paid_currency);
    }

    public function test_credit_top_up_converts_a_local_currency_to_usd_and_records_the_original(): void
    {
        Setting::setValue('pricing.ngn_rate_source', 'manual');
        Setting::setValue('pricing.manual_ngn_rate', 1500);
        app(CurrencyService::class)->flushNgnRate();
        $user = $this->user();

        $txn = $this->wallet->creditTopUp($user, 4500, 'NGN', ['description' => 'Top-up']);

        $this->assertSame('USD', $txn->currency);
        $this->assertSame('3.0000', (string) $txn->amount);
        $this->assertSame('4500.0000', (string) $txn->paid_amount);
        $this->assertSame('NGN', $txn->paid_currency);
        $this->assertSame('3.0000', (string) $user->wallet->fresh()->usd_balance);
        // The unified wallet never grows ngn_balance from a top-up.
        $this->assertSame('0.00', (string) $user->wallet->fresh()->ngn_balance);
    }

    public function test_credit_top_up_rejects_a_currency_currency_service_does_not_recognize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->wallet->creditTopUp($this->user(), 50, 'XYZ');
    }

    // ---- Production-readiness audit: DB-level (user_id, reference) unique
    // constraint backing the double-credit/replay guard ---------------------

    public function test_the_same_reference_for_two_different_users_is_allowed(): void
    {
        // The constraint is scoped per user, not global — a batch job that
        // reuses one reference string across many users (e.g. a period-based
        // billing reference) must never collide between different users.
        $a = $this->user();
        $b = $this->user();

        $txnA = $this->wallet->credit($a, 10, 'USD', ['reference' => 'shared-batch-ref']);
        $txnB = $this->wallet->credit($b, 10, 'USD', ['reference' => 'shared-batch-ref']);

        $this->assertNotSame($txnA->id, $txnB->id);
        $this->assertSame('shared-batch-ref', $txnA->reference);
        $this->assertSame('shared-batch-ref', $txnB->reference);
    }

    /** @return array<string, mixed> */
    private function txnRow(int $userId, ?string $reference): array
    {
        return [
            'user_id' => $userId,
            'type' => 'credit',
            'amount' => 5,
            'currency' => 'USD',
            'balance_before' => 0,
            'balance_after' => 5,
            'reference' => $reference,
            'status' => 'completed',
        ];
    }

    public function test_a_duplicate_reference_for_the_same_user_is_rejected_at_the_db_level(): void
    {
        // WalletService::apply() already short-circuits on a matching
        // (user_id, reference) before ever reaching a second insert, so this
        // exercises the DB constraint directly as the last-resort backstop.
        $user = $this->user();
        WalletTransaction::create($this->txnRow($user->id, 'dup-ref'));

        $this->expectException(QueryException::class);

        WalletTransaction::create($this->txnRow($user->id, 'dup-ref'));
    }

    public function test_multiple_null_references_for_the_same_user_are_allowed(): void
    {
        // A unique index treats each NULL as distinct — confirms that holds
        // here too, so any legitimate null-reference row is never blocked.
        $user = $this->user();

        WalletTransaction::create($this->txnRow($user->id, null));
        $second = WalletTransaction::create($this->txnRow($user->id, null));

        $this->assertNull($second->reference);
        $this->assertSame(2, WalletTransaction::where('user_id', $user->id)->count());
    }
}
