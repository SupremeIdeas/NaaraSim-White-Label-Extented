<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Jobs\AlertAdminJob;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use App\Notifications\RefundNotification;
use App\Services\Pricing\CurrencyService;
use App\Support\Auditor;
use App\Support\Mailer;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * WalletService — the single owner of wallet balance changes
 * (blueprint Sections 1.2 & 14.2). Every debit/credit is:
 *
 *   - Atomic: wrapped in a DB transaction with a pessimistic lockForUpdate on
 *     the wallet row, and always writes a wallet_transactions row recording
 *     balance_before / balance_after in the SAME transaction.
 *   - Serialized across processes: guarded by an atomic cache lock per wallet
 *     (Redis in production) so concurrent debits can never double-spend, even
 *     on a driver whose SELECT ... FOR UPDATE is a no-op.
 *   - Idempotent: passing a reference that already exists returns the existing
 *     transaction instead of charging again (money actions never blind-retry).
 *
 * Nothing else in the app mutates wallet balances directly.
 */
class WalletService
{
    /** Supported wallet currencies mapped to their balance column. */
    private const CURRENCY_COLUMNS = [
        'NGN' => 'ngn_balance',
        'USD' => 'usd_balance',
    ];

    private const SCALE = 4;

    /**
     * Debit a user's wallet. Throws InsufficientBalanceException if the
     * balance cannot cover the amount.
     *
     * @param  array<string, mixed>  $meta  Optional description/reference/idempotency_key.
     */
    public function debit(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'debit', $amount, $currency, $meta);
    }

    /** Credit a user's wallet (deposit/top-up). */
    public function credit(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'credit', $amount, $currency, $meta);
    }

    /**
     * Unified USD Wallet (blueprint Part B §3.2/§3.3, code change 4) — the ONE
     * entry point every top-up path should use, so a future caller can never
     * forget the conversion step. `usd_balance` is the one spendable balance;
     * regardless of what currency the user actually paid in, this converts to
     * USD at the authoritative rate (CurrencyService — the same rate used for
     * both display and payouts, so it can never disagree) before crediting.
     * The original payment is preserved on the ledger row (paid_amount/
     * paid_currency) for transparency ("Topped up $42.10 (₦65,000 via
     * Paystack)") without ever making it spendable separately.
     *
     * Deliberately does NOT use CurrencyService::rate()'s permissive "unknown
     * currency → treat as USD" fallback — an unrecognized currency here throws
     * InvalidArgumentException (same contract WalletService::credit() already
     * has), so CreditWalletJob's existing AlertAdminJob safety net still fires
     * rather than silently crediting the wrong amount at a 1:1 guess.
     */
    public function creditTopUp(User $user, float $localAmount, string $localCurrency, array $meta = []): WalletTransaction
    {
        $localCurrency = strtoupper($localCurrency);

        if (in_array($localCurrency, ['USD', 'USDT'], true)) {
            $usdAmount = $localAmount;
        } elseif (isset(CurrencyService::SUPPORTED[$localCurrency])) {
            $usdAmount = app(CurrencyService::class)->toUsd($localAmount, $localCurrency);
        } else {
            throw new \InvalidArgumentException("Unsupported top-up currency [{$localCurrency}].");
        }

        return $this->credit($user, round($usdAmount, self::SCALE), 'USD', [
            ...$meta,
            'paid_amount' => $localAmount,
            'paid_currency' => $localCurrency,
        ]);
    }

    /**
     * Refund a previous charge back to the wallet. Sends a best-effort refund
     * notice email — centralised here so every refund path (failed order, OTP
     * timeout, orphan-charge guard) tells the user their money is back. The
     * email dispatches AFTER the transaction commits, only on a genuinely new
     * refund row (idempotent replays don't re-email), and can be suppressed
     * with `meta['notify'] === false`.
     */
    public function refund(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        $txn = $this->apply($user, 'refund', $amount, $currency, $meta);

        if ($txn->wasRecentlyCreated && ($meta['notify'] ?? true)) {
            Mailer::notify($user, new RefundNotification(
                $amount,
                strtoupper($currency),
                $meta['description'] ?? null,
            ));
        }

        return $txn;
    }

    /** Credit a referral profit-share reward (store credit). */
    public function reward(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'referral', $amount, $currency, $meta);
    }

    /**
     * Charge-then-deliver with an orphan-charge guard: debit the wallet, run
     * the delivery callback, and if delivery throws, AUTO-REFUND and alert —
     * never charge without delivering (money-safety rule 1.2). The refund is
     * itself idempotent so this can't double-refund.
     *
     * @template T
     *
     * @param  Closure(WalletTransaction): T  $deliver
     * @return T
     */
    public function charge(User $user, float $amount, string $currency, Closure $deliver, array $meta = []): mixed
    {
        $debit = $this->debit($user, $amount, $currency, $meta);

        try {
            return $deliver($debit);
        } catch (Throwable $e) {
            $this->refund($user, $amount, $currency, [
                'description' => 'Auto-refund: downstream delivery failed',
                'reference' => 'refund:'.$debit->reference,
            ]);

            AlertAdminJob::dispatch(
                code: 'orphan_charge_refunded',
                message: "Charge to user {$user->id} was auto-refunded after delivery failed: {$e->getMessage()}",
                context: [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'debit_reference' => $debit->reference,
                    'exception' => $e::class,
                ],
            );

            throw new OrphanChargeRefundedException(
                "Charge to user {$user->id} auto-refunded after delivery failure.",
                $e,
            );
        }
    }

    /**
     * Reserve (earmark) USD for a future auto-charge (Merchant V2 client
     * auto-renewal). The money stays in the wallet but is subtracted from
     * spendable — every USD debit checks balance − reserved — so it "cannot be
     * reused for any other transaction" until settled or released. Atomic +
     * row-locked; throws if spendable can't cover it.
     */
    public function reserve(User $user, float $amount): void
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Reserve amount must be positive.');
        }

        Cache::lock("wallet:{$user->id}", 10)->block(5, function () use ($user, $amount) {
            DB::transaction(function () use ($user, $amount) {
                $wallet = $this->lockedWallet($user);
                $spendable = round((float) $wallet->usd_balance - (float) $wallet->reserved_usd, self::SCALE);
                if ($amount > $spendable) {
                    throw new InsufficientBalanceException($user->id, 'USD', $amount, $spendable);
                }
                $wallet->reserved_usd = round((float) $wallet->reserved_usd + $amount, self::SCALE);
                $wallet->save();
            });
        });

        Auditor::log('wallet.reserved', UserWallet::class, $user->id, ['amount' => $amount, 'currency' => 'USD']);
    }

    /**
     * Release a USD reservation back to spendable (never below zero). Used when
     * an auto-renewal settles into a real debit (release then charge) or when
     * provisioning fails and the earmark is freed.
     */
    public function release(User $user, float $amount): void
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            return;
        }

        Cache::lock("wallet:{$user->id}", 10)->block(5, function () use ($user, $amount) {
            DB::transaction(function () use ($user, $amount) {
                $wallet = $this->lockedWallet($user);
                $wallet->reserved_usd = round(max(0, (float) $wallet->reserved_usd - $amount), self::SCALE);
                $wallet->save();
            });
        });

        Auditor::log('wallet.reservation_released', UserWallet::class, $user->id, ['amount' => $amount, 'currency' => 'USD']);
    }

    public function reservedUsd(User $user): float
    {
        return round((float) ($user->wallet?->reserved_usd ?? 0), self::SCALE);
    }

    /** Spendable USD = balance − reserved. */
    public function spendableUsd(User $user): float
    {
        $wallet = $user->wallet;

        return round((float) ($wallet?->usd_balance ?? 0) - (float) ($wallet?->reserved_usd ?? 0), self::SCALE);
    }

    /** Fetch the wallet row under a pessimistic lock (creating it if missing). */
    private function lockedWallet(User $user): UserWallet
    {
        $wallet = UserWallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
            ?? UserWallet::create(['user_id' => $user->id]);

        return UserWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->first();
    }

    /**
     * The atomic core. Locks the wallet (cache lock + row lock), reads the
     * balance, applies the delta, and writes the paired transaction row.
     */
    private function apply(User $user, string $type, float $amount, string $currency, array $meta): WalletTransaction
    {
        $currency = strtoupper($currency);
        $column = self::CURRENCY_COLUMNS[$currency]
            ?? throw new \InvalidArgumentException("Unsupported wallet currency [$currency].");

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be positive.');
        }
        $amount = round($amount, self::SCALE);

        $reference = $meta['reference'] ?? $meta['idempotency_key'] ?? null;

        // Cross-process mutual exclusion per wallet (Redis in prod; array store
        // is per-process which is sufficient for single-process test runs).
        return Cache::lock("wallet:{$user->id}", 10)->block(5, function () use ($user, $type, $amount, $currency, $column, $meta, $reference) {
            return DB::transaction(function () use ($user, $type, $amount, $currency, $column, $meta, $reference) {
                // Idempotency: a matching reference means this action already
                // ran — return it rather than moving money twice.
                if ($reference !== null) {
                    $existing = WalletTransaction::query()
                        ->where('user_id', $user->id)
                        ->where('reference', $reference)
                        ->first();
                    if ($existing !== null) {
                        return $existing;
                    }
                }

                /** @var UserWallet $wallet */
                $wallet = UserWallet::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first()
                    ?? UserWallet::create(['user_id' => $user->id]);

                // Re-fetch with the lock if the row was just created.
                $wallet = UserWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->first();

                $before = round((float) $wallet->{$column}, self::SCALE);
                $isDebit = $type === 'debit';
                $delta = $isDebit ? -$amount : $amount;
                $after = round($before + $delta, self::SCALE);

                // A debit can never dip into reserved (earmarked) funds. For USD
                // the floor is the reserved amount; other currencies have none.
                $floor = ($isDebit && $currency === 'USD')
                    ? round((float) $wallet->reserved_usd, self::SCALE)
                    : 0.0;
                if ($isDebit && $after < $floor) {
                    throw new InsufficientBalanceException($user->id, $currency, $amount, round($before - $floor, self::SCALE));
                }

                $wallet->{$column} = $after;
                if ($isDebit) {
                    $wallet->total_spent = round((float) $wallet->total_spent + $amount, 2);
                } elseif ($type === 'credit') {
                    // Only genuine deposits/top-ups grow lifetime deposits;
                    // refunds and referral rewards do not.
                    $wallet->total_deposits = round((float) $wallet->total_deposits + $amount, 2);
                }
                $wallet->save();

                return WalletTransaction::create([
                    'user_id' => $user->id,
                    // Prompt 11 §3 (shared-wallet plans): set only via meta, by
                    // WalletGroupService — a purely additive optional key, no
                    // signature change to any public method here. Null for
                    // every ordinary, non-group transaction.
                    'spent_by_user_id' => $meta['spent_by_user_id'] ?? null,
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => $currency,
                    'paid_amount' => $meta['paid_amount'] ?? null,
                    'paid_currency' => isset($meta['paid_currency']) ? strtoupper((string) $meta['paid_currency']) : null,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference' => $reference ?? (string) Str::uuid(),
                    'description' => $meta['description'] ?? null,
                    'status' => 'completed',
                ]);
            });
        });
    }
}
