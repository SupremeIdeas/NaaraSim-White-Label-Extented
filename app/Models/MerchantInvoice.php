<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's own invoice to one of their clients (Merchant V2 billing). Purely
 * a bookkeeping record — the client pays the merchant directly (bank transfer,
 * cash, WhatsApp payment link), never through a NaaraSim wallet, so nothing here
 * ever touches WalletService.
 */
class MerchantInvoice extends Model
{
    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $fillable = [
        'merchant_id', 'merchant_client_id', 'merchant_client_subscription_id',
        'description', 'amount', 'status', 'due_at', 'sent_at', 'viewed_at',
        'paid_at', 'reference', 'public_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class, 'merchant_client_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MerchantClientSubscription::class, 'merchant_client_subscription_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::SENT && $this->due_at !== null && $this->due_at->isPast();
    }

    /** Days between sending and payment — null until paid. */
    public function daysToPay(): ?int
    {
        if ($this->paid_at === null || $this->sent_at === null) {
            return null;
        }

        return $this->sent_at->diffInDays($this->paid_at);
    }
}
