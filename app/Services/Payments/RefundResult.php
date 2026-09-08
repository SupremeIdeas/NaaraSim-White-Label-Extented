<?php

namespace App\Services\Payments;

/**
 * Outcome of a gateway-side refund call (BUILD-2 §7.1). `ok` means the provider
 * accepted the refund; `providerRef` is the provider's refund id (for the audit
 * trail); `error` carries a safe message when it failed.
 */
class RefundResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerRef = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(?string $providerRef = null): self
    {
        return new self(true, $providerRef);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
