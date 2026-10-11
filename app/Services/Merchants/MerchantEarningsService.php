<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single owner of a merchant's reseller-earnings balance (ROADMAP §Layer
 * 3.4). Every accrual / hold / release is atomic (cache lock + DB transaction),
 * writes a ledger row with balance_after, and is idempotent on its reference —
 * so a replayed purchase webhook or a retried withdrawal never moves the balance
 * twice. Mirrors WalletService's discipline for the merchant bucket.
 */
class MerchantEarningsService
{
    private const SCALE = 4;

    /**
     * Accrue the reseller margin a merchant earned on a customer's purchase: the
     * cash collected ABOVE retail (never the admin's own margin). A non-positive
     * earning (e.g. a coupon pulled the price back to retail) records nothing.
     */
    public function accrue(Merchant $merchant, User $customer, string $sourceType, float $retail, float $charged, string $reference): ?MerchantEarning
    {
        $earning = round(max(0.0, $charged - $retail), self::SCALE);
        if ($earning <= 0) {
            return null;
        }

        return $this->apply($merchant, MerchantEarning::ACCRUAL, $earning, $reference, [
            'source_type' => $sourceType,
            'source_user_id' => $customer->id,
            'description' => 'Reseller margin on '.$sourceType.' sale',
        ]);
    }

    /**
     * Hold earnings for a withdrawal (a negative movement). Throws if the balance
     * can't cover it, so a cash-out can never overdraw the bucket.
     *
     * @throws MerchantException
     */
    public function hold(Merchant $merchant, float $amount, string $reference, ?string $description = null): MerchantEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new MerchantException('Withdrawal amount must be positive.');
        }

        return $this->apply($merchant, MerchantEarning::HOLD, -$amount, $reference, [
            'description' => $description ?? 'Withdrawal hold',
        ]);
    }

    /** Debit for a member-to-member transfer (escrow). Throws if it would overdraw. Idempotent on the reference. */
    public function transferOut(Merchant $merchant, float $amount, string $reference, string $description): MerchantEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new MerchantException('Transfer amount must be positive.');
        }

        return $this->apply($merchant, MerchantEarning::TRANSFER_OUT, -$amount, $reference, ['description' => $description]);
    }

    /** Return held earnings after a failed/reversed payout (idempotent). */
    public function release(Merchant $merchant, float $amount, string $reference, ?string $description = null): MerchantEarning
    {
        return $this->apply($merchant, MerchantEarning::RELEASE, round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Withdrawal returned',
        ]);
    }

    /**
     * Reverse an earlier accrual after a refund or a lost chargeback (admin-triggered — disputes are never automated).
     * Unlike a hold this MAY take the balance negative: the shortfall is a debt that future earnings repay, and while
     * it exists the balance cannot be withdrawn (a hold would overdraw). Idempotent on the reference.
     */
    public function clawback(Merchant $merchant, float $amount, string $reference, string $reason): MerchantEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new MerchantException('Clawback amount must be positive.');
        }

        return $this->apply($merchant, MerchantEarning::CLAWBACK, -$amount, $reference, ['description' => 'Adjustment: '.$reason]);
    }

    /** Money the merchant owes back (0 when the balance is not negative). */
    public function debt(Merchant $merchant): float
    {
        return round(max(0.0, -$this->balance($merchant)), self::SCALE);
    }

    /** Available earnings balance (USD). */
    public function balance(Merchant $merchant): float
    {
        $last = MerchantEarning::where('merchant_id', $merchant->id)->latest('id')->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    /**
     * The atomic core: lock the merchant's ledger, read the running balance, apply
     * the signed delta, and append the row. Idempotent on the reference.
     */
    private function apply(Merchant $merchant, string $type, float $delta, string $reference, array $meta): MerchantEarning
    {
        return Cache::lock("merchant-earnings:{$merchant->id}", 10)->block(5, function () use ($merchant, $type, $delta, $reference, $meta) {
            return DB::transaction(function () use ($merchant, $type, $delta, $reference, $meta) {
                $existing = MerchantEarning::where('reference', $reference)->first();
                if ($existing !== null) {
                    return $existing; // already applied — never move the balance twice
                }

                $before = (float) (MerchantEarning::where('merchant_id', $merchant->id)
                    ->latest('id')->value('balance_after') ?? 0);
                $after = round($before + $delta, self::SCALE);

                if ($delta < 0 && $after < 0 && $type !== MerchantEarning::CLAWBACK) { // a debit may not overdraw; a credit may still leave a debt partly repaid
                    throw new MerchantException('Amount exceeds the merchant’s available earnings.');
                }

                return MerchantEarning::create([
                    'merchant_id' => $merchant->id,
                    'type' => $type,
                    'amount' => round($delta, self::SCALE),
                    'balance_after' => $after,
                    'currency' => 'USD',
                    'reference' => $reference,
                    'source_type' => $meta['source_type'] ?? null,
                    'source_user_id' => $meta['source_user_id'] ?? null,
                    'description' => $meta['description'] ?? null,
                ]);
            });
        });
    }
}
