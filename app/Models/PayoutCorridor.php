<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One payable lane: <currency> into <country> via <provider> using <method>
 * (Global Payout Layer, Phase 1). Ships DISABLED; an admin enables it once the
 * provider sandbox proves it. `est_provider_cost_bps` is admin-only (money rule 2).
 */
class PayoutCorridor extends Model
{
    protected $fillable = [
        'country', 'currency', 'provider', 'method', 'enabled', 'priority', 'min_usd', 'max_usd',
        'platform_fee_bps', 'platform_fee_flat_usd', 'est_provider_cost_bps', 'fixed_fee_usd_est', 'eta_text',
        'requires_kyc_level', 'destination_types', 'notes',
    ];

    /** Never serialised to a user-facing payload. */
    protected $hidden = ['est_provider_cost_bps', 'fixed_fee_usd_est'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'min_usd' => 'decimal:2',
            'max_usd' => 'decimal:2',
            'platform_fee_flat_usd' => 'decimal:2',
            'fixed_fee_usd_est' => 'decimal:2',
            'destination_types' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // The rail guide derives "we have it switched on" from the corridors — keep its cache honest.
        $flush = fn () => app(\App\Services\Payouts\Rail\PayoutRailRegistry::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function scopeEnabled(Builder $q): Builder
    {
        return $q->where('enabled', true);
    }

    /** The platform fee (USD) charged on a gross USD amount — what the user is shown. */
    public function feeUsd(float $usd): float
    {
        return round($usd * ($this->platform_fee_bps / 10000) + (float) $this->platform_fee_flat_usd, 4);
    }

    /**
     * The smallest USD amount this corridor can carry before the provider's fixed fee eats more than
     * `payouts.max_fee_ratio` of it (Addendum D-3.15); null when there is no fee estimate or the guard is off.
     */
    public function economicMinUsd(): ?float
    {
        $ratio = \App\Support\PayoutSettings::maxFeeRatio();
        if ($ratio <= 0 || $this->fixed_fee_usd_est === null || (float) $this->fixed_fee_usd_est <= 0) {
            return null;
        }

        return round((float) $this->fixed_fee_usd_est / $ratio, 2);
    }

    /** Whether a USD amount is inside this corridor's per-request limits. */
    public function allows(float $usd): bool
    {
        $econ = $this->economicMinUsd();

        return ($econ === null || $usd >= $econ)
            && ($this->min_usd === null || $usd >= (float) $this->min_usd)
            && ($this->max_usd === null || $usd <= (float) $this->max_usd);
    }
}
