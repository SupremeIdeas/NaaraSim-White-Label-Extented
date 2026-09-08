<?php

namespace App\Services\SMS;

use App\Models\SmsOrder;

/**
 * Result of a successful SmsNumberRouter order. `cost` and `profit` are
 * internal-only and must never be surfaced to end users.
 */
class SmsOrderResult
{
    public function __construct(
        public readonly string $provider,
        public readonly SmsOrder $order,
        public readonly array $payload,
        public readonly float $cost,
        public readonly float $retail,
    ) {}

    public static function success(string $provider, SmsOrder $order, array $payload, float $cost, float $retail): self
    {
        return new self($provider, $order, $payload, $cost, $retail);
    }
}
