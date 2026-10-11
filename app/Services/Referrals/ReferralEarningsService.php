<?php

namespace App\Services\Referrals;

use RuntimeException;
use App\Models\ReferralEarning;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The referrer's real (withdrawable) earnings ledger (NAARA-BUILD-22 §1).
 * Mirrors MerchantEarningsService exactly: per-user Cache::lock + DB::transaction,
 * idempotent on the reference, every row records balance_after. Reused by the
 * first-transaction reward trigger and by the shared payout dashboard's
 * hold/release on withdrawal.
 */
class ReferralEarningsService
{
    private const SCALE = 4;

    /** Credit a referrer's earning (idempotent on $reference). Non-positive is a no-op. */
    public function accrue(User $referrer, ?User $referred, string $sourceType, float $amount, string $reference): ?ReferralEarning
    {
        $amount = round(max(0.0, $amount), self::SCALE);
        if ($amount <= 0) {
            return null;
        }

        return $this->apply($referrer, ReferralEarning::ACCRUAL, $amount, $reference, [
            'source_type' => $sourceType,
            'source_user_id' => $referred?->id,
            'description' => 'Referral margin share on '.$sourceType.' sale',
        ]);
    }

    /**
     * Hold earnings for a withdrawal (a negative movement). Throws if the balance
     * can't cover it, so a cash-out can never overdraw the bucket.
     *
     * @throws RuntimeException
     */
    public function hold(User $referrer, float $amount, string $reference, ?string $description = null): ReferralEarning
    {
        return $this->apply($referrer, ReferralEarning::HOLD, -round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Payout hold',
        ]);
    }

    /** Return held earnings after a failed/reversed payout (idempotent). */
    public function release(User $referrer, float $amount, string $reference, ?string $description = null): ReferralEarning
    {
        return $this->apply($referrer, ReferralEarning::RELEASE, round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Payout released',
        ]);
    }

    /**
     * Reverse an earlier accrual after a refund or a lost chargeback (admin-triggered — disputes are never automated).
     * May take the balance negative: a debt that future earnings repay; withdrawals are blocked while it exists.
     */
    public function clawback(User $referrer, float $amount, string $reference, string $reason): ReferralEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new RuntimeException('Clawback amount must be positive.');
        }

        return $this->apply($referrer, ReferralEarning::CLAWBACK, -$amount, $reference, ['description' => 'Adjustment: '.$reason]);
    }

    /** Debit for a member-to-member transfer (escrow). Throws if it would overdraw. Idempotent on the reference. */
    public function transferOut(User $sender, float $amount, string $reference, string $description): ReferralEarning
    {
        return $this->apply($sender, ReferralEarning::TRANSFER_OUT, -round($amount, self::SCALE), $reference, ['description' => $description]);
    }

    /** Credit a transfer the member accepted. Not an accrual. Idempotent on the reference. */
    public function transferIn(User $recipient, float $amount, string $reference, string $description, ?User $from = null): ReferralEarning
    {
        return $this->apply($recipient, ReferralEarning::TRANSFER_IN, round($amount, self::SCALE), $reference, ['description' => $description, 'source_type' => 'transfer', 'source_user_id' => $from?->id]);
    }

    /** Money the referrer owes back (0 when the balance is not negative). */
    public function debt(User $referrer): float
    {
        return round(max(0.0, -$this->balance($referrer)), self::SCALE);
    }

    public function balance(User $referrer): float
    {
        $last = ReferralEarning::where('user_id', $referrer->id)->latest('id')->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    private function apply(User $referrer, string $type, float $delta, string $reference, array $meta): ReferralEarning
    {
        return Cache::lock("referral-earnings:{$referrer->id}", 10)->block(5, function () use ($referrer, $type, $delta, $reference, $meta) {
            return DB::transaction(function () use ($referrer, $type, $delta, $reference, $meta) {
                $existing = ReferralEarning::where('reference', $reference)->first();
                if ($existing !== null) {
                    return $existing; // already applied — never move the balance twice
                }

                $before = (float) (ReferralEarning::where('user_id', $referrer->id)
                    ->latest('id')->value('balance_after') ?? 0);
                $after = round($before + $delta, self::SCALE);

                if ($delta < 0 && $after < 0 && $type !== ReferralEarning::CLAWBACK) { // a debit may not overdraw; a credit may still leave a debt partly repaid
                    throw new RuntimeException('Amount exceeds the referrer’s available earnings.');
                }

                return ReferralEarning::create([
                    'user_id' => $referrer->id,
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
