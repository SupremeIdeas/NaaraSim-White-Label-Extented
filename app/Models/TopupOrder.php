<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A top-up purchase and its state machine. Provider identity and cost are hidden from user payloads. */
class TopupOrder extends Model
{
    public const CREATED = 'created';

    public const FUNDS_HELD = 'funds_held';

    public const SUBMITTING = 'submitting';

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    public const PAYMENT_FAILED = 'payment_failed';

    /** @var array<string, list<string>> forward-only transitions */
    public const TRANSITIONS = [
        self::CREATED => [self::FUNDS_HELD, self::PAYMENT_FAILED],
        self::FUNDS_HELD => [self::SUBMITTING, self::FAILED, self::REFUNDED],
        self::SUBMITTING => [self::PENDING, self::DELIVERED, self::FAILED, self::SUBMITTING],
        self::PENDING => [self::DELIVERED, self::FAILED, self::SUBMITTING],
        self::FAILED => [self::REFUNDED],
        self::DELIVERED => [],
        self::REFUNDED => [],
        self::PAYMENT_FAILED => [],
    ];

    public const TERMINAL = [self::DELIVERED, self::REFUNDED, self::PAYMENT_FAILED];

    protected $fillable = [
        'uuid', 'user_id', 'quote_id', 'product_id', 'phone_e164', 'country_iso', 'operator_name', 'product_type', 'product_name',
        'send_amount', 'send_currency', 'provider_code', 'provider_sku', 'reference', 'provider_txn_id', 'status', 'price_usd', 'list_price_usd', 'credit_usd', 'credits',
        'cost_usd', 'cost_native', 'cost_currency', 'wallet_ref', 'attempts', 'requery_count', 'last_requery_at', 'submitted_at',
        'delivered_at', 'escalated', 'failure_reason', 'idempotency_key', 'meta',
    ];

    protected $hidden = ['provider_code', 'provider_sku', 'provider_txn_id', 'reference', 'cost_usd', 'cost_native', 'cost_currency', 'meta'];

    protected function casts(): array
    {
        return [
            'send_amount' => 'decimal:2', 'price_usd' => 'decimal:4', 'list_price_usd' => 'decimal:4', 'credit_usd' => 'decimal:2', 'credits' => 'decimal:2', 'cost_usd' => 'decimal:4', 'cost_native' => 'decimal:4',
            'last_requery_at' => 'datetime', 'submitted_at' => 'datetime', 'delivered_at' => 'datetime',
            'escalated' => 'boolean', 'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TopupProduct::class, 'product_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TopupOrderEvent::class, 'order_id')->orderBy('id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function canMoveTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
