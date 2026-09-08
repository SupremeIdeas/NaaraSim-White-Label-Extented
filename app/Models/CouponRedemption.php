<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One coupon use on one order — the audit trail for every discount given. */
class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'product',
        'reference',
        'list_price',
        'paid_price',
        'amount_saved',
        'floor_clamped',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:4',
            'paid_price' => 'decimal:4',
            'amount_saved' => 'decimal:4',
            'floor_clamped' => 'boolean',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
