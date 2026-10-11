<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One point of the Funding Radar time series. Append-only. */
class PayoutExposureSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'low_confidence' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('payout_exposure_snapshots is append-only.'));
    }
}
