<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A price lock (default 5 minutes). The order always uses the quote price; it is never re-priced after payment. */
class TopupQuote extends Model
{
    protected $fillable = ['user_id', 'product_id', 'phone_e164', 'send_amount', 'send_currency', 'cost_usd', 'price_usd', 'list_price_usd', 'credit_usd', 'credits', 'markup_pct', 'expires_at'];

    protected $hidden = ['cost_usd', 'markup_pct'];

    protected function casts(): array
    {
        return ['send_amount' => 'decimal:2', 'cost_usd' => 'decimal:4', 'price_usd' => 'decimal:4', 'list_price_usd' => 'decimal:4', 'credit_usd' => 'decimal:2', 'credits' => 'decimal:2', 'markup_pct' => 'decimal:4', 'expires_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TopupProduct::class, 'product_id');
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }
}
