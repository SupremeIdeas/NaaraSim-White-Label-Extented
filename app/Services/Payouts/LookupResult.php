<?php

namespace App\Services\Payouts;

/** Answer to a provider status lookup. */
final class LookupResult
{
    public const FOUND = 'found';

    public const NOT_FOUND = 'not_found';

    public const UNSUPPORTED = 'unsupported';

    public function __construct(
        public readonly string $state,              // found | not_found | unsupported
        public readonly ?string $status = null,     // when found: paid | failed | processing
        public readonly ?string $providerRef = null,
        public readonly ?string $failureReason = null,
    ) {
    }

    public static function found(string $status, ?string $providerRef = null, ?string $reason = null): self
    {
        return new self(self::FOUND, $status, $providerRef, $reason);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function unsupported(): self
    {
        return new self(self::UNSUPPORTED);
    }
}
