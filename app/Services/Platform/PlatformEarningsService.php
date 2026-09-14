<?php

namespace App\Services\Platform;

use App\Models\PlatformEarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Prompt 21-EXT §5 — the single owner of the platform's white-label license
 * earnings balance. Mirrors MerchantEarningsService's discipline exactly
 * (atomic cache-lock + DB transaction, ledger row with balance_after,
 * idempotent on reference) but for ONE global bucket instead of one per
 * merchant — there is no owning user/merchant, only a running total.
 *
 * This ledger is deliberately never read by or merged into any general
 * platform-profit reporting: white-label sale proceeds are a separate pool
 * on purpose (owner's explicit instruction).
 */
class PlatformEarningsService
{
    private const SCALE = 4;

    private const LOCK_KEY = 'platform-earnings';

    /** Record a white-label license sale as platform revenue. Unlike
     *  MerchantEarningsService::accrue() (which nets charged − retail), this
     *  is a flat platform-product sale — the WHOLE amount charged is
     *  platform revenue, there's no underlying wholesale cost to net out. */
    public function accrue(float $amount, string $sourceType, string $reference, ?string $description = null): ?PlatformEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            return null;
        }

        return $this->apply(PlatformEarning::ACCRUAL, $amount, $reference, [
            'source_type' => $sourceType,
            'description' => $description ?? 'White-label license sale',
        ]);
    }

    /** Hold earnings for a withdrawal. Throws if the balance can't cover it. */
    public function hold(float $amount, string $reference, ?string $description = null): PlatformEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new PlatformEarningsException('Withdrawal amount must be positive.');
        }

        return $this->apply(PlatformEarning::HOLD, -$amount, $reference, [
            'description' => $description ?? 'Withdrawal hold',
        ]);
    }

    /** Return held earnings after a failed/reversed payout (idempotent). */
    public function release(float $amount, string $reference, ?string $description = null): PlatformEarning
    {
        return $this->apply(PlatformEarning::RELEASE, round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Withdrawal returned',
        ]);
    }

    /** Available platform earnings balance (USD). */
    public function balance(): float
    {
        return (float) (PlatformEarning::latest('id')->value('balance_after') ?? 0);
    }

    private function apply(string $type, float $delta, string $reference, array $meta): PlatformEarning
    {
        return Cache::lock(self::LOCK_KEY, 10)->block(5, function () use ($type, $delta, $reference, $meta) {
            return DB::transaction(function () use ($type, $delta, $reference, $meta) {
                $existing = PlatformEarning::where('reference', $reference)->first();
                if ($existing !== null) {
                    return $existing; // already applied — never move the balance twice
                }

                $before = (float) (PlatformEarning::latest('id')->value('balance_after') ?? 0);
                $after = round($before + $delta, self::SCALE);

                if ($after < 0) {
                    throw new PlatformEarningsException('Amount exceeds the platform’s available earnings.');
                }

                return PlatformEarning::create([
                    'type' => $type,
                    'amount' => round($delta, self::SCALE),
                    'balance_after' => $after,
                    'currency' => 'USD',
                    'reference' => $reference,
                    'source_type' => $meta['source_type'] ?? null,
                    'description' => $meta['description'] ?? null,
                ]);
            });
        });
    }
}
