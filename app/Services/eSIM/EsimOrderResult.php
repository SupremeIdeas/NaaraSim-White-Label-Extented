<?php

namespace App\Services\eSIM;

/**
 * Result of a successful ProviderRouter order (blueprint Section 6). Carries
 * the winning provider, its raw payload, and the money breakdown. `cost` and
 * `profit` are internal-only and must never be surfaced to end users.
 */
class EsimOrderResult
{
    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
        public readonly float $cost,
        public readonly float $charged,
        public readonly float $profit,
        /** The provider's own SKU for the plan actually fulfilled — the same
         *  value passed to orderBundle(). Needed later for getUsage($iccid,
         *  $bundleName) on providers whose endpoint requires it (eSIM Go). */
        public readonly ?string $providerPlanId = null,
    ) {}

    public static function success(string $provider, array $payload, float $cost, float $charged, ?string $providerPlanId = null): self
    {
        return new self(
            provider: $provider,
            payload: $payload,
            cost: $cost,
            charged: $charged,
            profit: round($charged - $cost, 4),
            providerPlanId: $providerPlanId,
        );
    }

    /** User-safe view — never includes cost or profit. */
    public function toPublicArray(): array
    {
        return [
            'provider' => $this->provider,
            'payload' => $this->payload,
        ];
    }
}
