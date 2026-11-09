<?php

namespace App\Services\Topup\DTO;

final class PurchaseResult
{
    public const DELIVERED = 'delivered';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    public function __construct(
        public string $status,
        public ?string $reference = null,       // our reference (webhooks identify the order by it)
        public ?string $providerTxnId = null,
        public ?string $providerCode = null,
        public ?string $message = null,
        public ?float $costCharged = null,
        public ?string $costCurrency = null,
        public bool $definitive = false,        // FAILED only: true = nothing was sent, safe to fail over / refund
        public ?string $receiptText = null,
        public array $raw = [],
    ) {}

    public function terminal(): bool
    {
        return in_array($this->status, [self::DELIVERED, self::FAILED, self::REVERSED], true);
    }
}
