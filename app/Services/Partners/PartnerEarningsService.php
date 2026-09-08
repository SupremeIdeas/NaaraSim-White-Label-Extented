<?php

namespace App\Services\Partners;

use App\Models\Partner;
use App\Models\PartnerEarning;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single owner of a partner's profit-share balance. Same atomic discipline
 * as MerchantEarningsService (cache lock + DB transaction, idempotent on the
 * reference, ledger row with balance_after) — kept as a SEPARATE service so the
 * two programs' earnings logic can never entangle.
 */
class PartnerEarningsService
{
    private const SCALE = 4;

    /**
     * Accrue a period's profit-share for a partner (idempotent on the reference,
     * so a re-run of the same period never pays twice). Records the period on the
     * ledger row for audit. A non-positive share books nothing.
     */
    public function accrue(Partner $partner, float $amount, string $reference, Carbon $periodStart, Carbon $periodEnd, ?string $description = null): ?PartnerEarning
    {
        $amount = round(max(0.0, $amount), self::SCALE);
        if ($amount <= 0) {
            return null;
        }

        return $this->apply($partner, PartnerEarning::ACCRUAL, $amount, $reference, [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'description' => $description ?? 'Profit-share for '.$periodStart->toDateString().' – '.$periodEnd->toDateString(),
        ]);
    }

    /** Hold earnings for a payout (a negative movement). Throws if short. */
    public function hold(Partner $partner, float $amount, string $reference, ?string $description = null): PartnerEarning
    {
        $amount = round($amount, self::SCALE);
        if ($amount <= 0) {
            throw new PartnerException('Payout amount must be positive.');
        }

        return $this->apply($partner, PartnerEarning::HOLD, -$amount, $reference, [
            'description' => $description ?? 'Payout hold',
        ]);
    }

    /** Return held earnings after a failed/reversed payout (idempotent). */
    public function release(Partner $partner, float $amount, string $reference, ?string $description = null): PartnerEarning
    {
        return $this->apply($partner, PartnerEarning::RELEASE, round($amount, self::SCALE), $reference, [
            'description' => $description ?? 'Payout returned',
        ]);
    }

    /** Available profit-share balance (USD). */
    public function balance(Partner $partner): float
    {
        $last = PartnerEarning::where('partner_id', $partner->id)->latest('id')->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    /**
     * The atomic core: lock the partner's ledger, read the running balance, apply
     * the signed delta, append the row. Idempotent on the reference.
     */
    private function apply(Partner $partner, string $type, float $delta, string $reference, array $meta): PartnerEarning
    {
        return Cache::lock("partner-earnings:{$partner->id}", 10)->block(5, function () use ($partner, $type, $delta, $reference, $meta) {
            return DB::transaction(function () use ($partner, $type, $delta, $reference, $meta) {
                $existing = PartnerEarning::where('reference', $reference)->first();
                if ($existing !== null) {
                    return $existing; // already applied — never move the balance twice
                }

                $before = (float) (PartnerEarning::where('partner_id', $partner->id)
                    ->latest('id')->value('balance_after') ?? 0);
                $after = round($before + $delta, self::SCALE);

                if ($after < 0) {
                    throw new PartnerException('Amount exceeds the partner’s available earnings.');
                }

                return PartnerEarning::create([
                    'partner_id' => $partner->id,
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
