<?php

namespace App\Services\Payouts\Guardian;

final class HoldCheck
{
    public const OK = 'ok';

    public const MISSING = 'missing';

    public const MISMATCH = 'mismatch';

    public const RELEASED = 'released';

    public const NEGATIVE = 'negative_balance';

    /** The bucket has no ledger to verify against (e.g. admin core margin) — a human must look. */
    public const UNVERIFIABLE = 'unverifiable';

    /** @param  array<string, mixed>  $evidence */
    public function __construct(public readonly string $status, public readonly array $evidence = []) {}

    public function ok(): bool
    {
        return $this->status === self::OK;
    }
}
