<?php

namespace App\Services\Kyc;

/**
 * The outcome of submitting a verification to a provider. `approved`/`rejected`
 * are synchronous decisions; `pending` means the provider will confirm via a
 * webhook (or a human reviews it, for the manual provider). `redirectUrl` is set
 * when the provider hosts the capture flow (doc + liveness).
 *
 * @phpstan-type ChecksArray array<string, mixed>
 */
final class KycResult
{
    /** @param array<string, mixed> $checks */
    public function __construct(
        public readonly string $status,          // approved | rejected | pending | failed
        public readonly array $checks = [],
        public readonly ?string $reason = null,
        public readonly ?string $redirectUrl = null,
    ) {
    }
}
