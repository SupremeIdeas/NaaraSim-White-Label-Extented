<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Naara Gift purchase (Phase 3). Provider identity is $hidden (scrub rule);
 * the receipt drives the three-state redemption screen.
 */
class GiftCardOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REVIEW = 'review';

    protected $fillable = [
        'user_id', 'gift_card_product_id', 'provider', 'provider_product_id', 'brand_name',
        'face_value', 'currency', 'price_charged', 'status', 'transaction_ref',
        'provider_tx_id', 'receipt', 'fields', 'review_reason',
    ];

    protected $hidden = ['provider', 'provider_product_id'];

    protected function casts(): array
    {
        return [
            'face_value' => 'decimal:2',
            'price_charged' => 'decimal:4',
            'receipt' => 'array',
            'fields' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(GiftCardProduct::class, 'gift_card_product_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /** Which redemption UI to show, from the receipt's populated fields. */
    public function redemptionMode(): string
    {
        $r = (array) $this->receipt;
        if (! empty($r['redemption_url'])) {
            return 'link';
        }
        if (! empty($r['account_id'])) {
            return 'account';
        }
        if (! empty($r['epin']) || ! empty($r['code'])) {
            return 'code';
        }

        return 'pending';
    }
}
