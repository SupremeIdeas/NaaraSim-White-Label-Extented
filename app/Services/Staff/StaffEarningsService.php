<?php

namespace App\Services\Staff;

use App\Models\StaffEarning;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A staff member's profit-share earnings ledger (NAARA-BUILD-23). The exact same
 * accrual pattern as PartnerEarningsService — per-user Cache::lock +
 * DB::transaction, idempotent on the reference, every row records balance_after —
 * applied to staff instead of partners. Reused by the monthly-close command and
 * the shared payout dashboard's hold/release on withdrawal.
 */
class StaffEarningsService
{
    private const SCALE = 4;

    /** Accrue a period's profit-share (idempotent on $reference). Non-positive = no-op. */
    public function accrue(User $staff, float $amount, string $reference, Carbon $periodStart, Carbon $periodEnd, ?string $description = null): ?StaffEarning
    {
        $amount = round(max(0.0, $amount), self::SCALE);
        if ($amount <= 0) {
            return null;
        }

        return $this->apply($staff, StaffEarning::ACCRUAL, $amount, $reference, [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'description' => $description ?? 'Profit-share for '.$periodStart->toDateString().' – '.$periodEnd->toDateString(),
        ]);
    }

    public function hold(User $staff, float $amount, string $reference, ?string $description = null): StaffEarning
    {
        return $this->apply($staff, StaffEarning::HOLD, -round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Payout hold',
        ]);
    }

    public function release(User $staff, float $amount, string $reference, ?string $description = null): StaffEarning
    {
        return $this->apply($staff, StaffEarning::RELEASE, round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Payout returned',
        ]);
    }

    public function balance(User $staff): float
    {
        $last = StaffEarning::where('user_id', $staff->id)->latest('id')->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    private function apply(User $staff, string $type, float $delta, string $reference, array $meta): StaffEarning
    {
        return Cache::lock("staff-earnings:{$staff->id}", 10)->block(5, function () use ($staff, $type, $delta, $reference, $meta) {
            return DB::transaction(function () use ($staff, $type, $delta, $reference, $meta) {
                $existing = StaffEarning::where('reference', $reference)->first();
                if ($existing !== null) {
                    return $existing; // already applied — never move the balance twice
                }

                $before = (float) (StaffEarning::where('user_id', $staff->id)
                    ->latest('id')->value('balance_after') ?? 0);
                $after = round($before + $delta, self::SCALE);

                if ($after < 0) {
                    throw new RuntimeException('Amount exceeds the staff member’s available earnings.');
                }

                return StaffEarning::create([
                    'user_id' => $staff->id,
                    'type' => $type,
                    'amount' => round($delta, self::SCALE),
                    'balance_after' => $after,
                    'currency' => 'USD',
                    'reference' => $reference,
                    'period_start' => $meta['period_start'] ?? null,
                    'period_end' => $meta['period_end'] ?? null,
                    'description' => $meta['description'] ?? null,
                ]);
            });
        });
    }
}
