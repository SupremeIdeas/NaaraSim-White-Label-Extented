<?php

namespace App\Services\Kyc;

/**
 * A normalised KYC webhook event (mirrors the payment/payout event DTOs). Only
 * the final decision matters to the gate: approved | rejected.
 */
final class KycEvent
{
    /** @param array<string, mixed> $checks */
    public function __construct(
        public readonly string $provider,
        public readonly string $reference,     // our kyc_verifications.reference
        public readonly string $status,        // approved | rejected
        public readonly array $checks = [],
        public readonly ?string $reason = null,
    ) {
    }
}
