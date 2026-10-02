<?php

namespace App\Services\Payouts\Guardian;

use Carbon\CarbonInterface;

/**
 * The outcome of one Guardian rule: pass | warn | fail, plus the evidence that
 * justified it. On `fail`, `outcome` says what the failure means (defer / hold /
 * reject). Evidence is for ADMINS and the decision log only — never shown to users.
 */
final class GateResult
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const DEFER = 'defer';

    public const HOLD = 'hold';

    public const REJECT = 'reject';

    /** @param  array<string, mixed>  $evidence */
    private function __construct(
        public readonly string $id,
        public readonly string $result,
        public readonly ?string $outcome,
        public readonly string $reason,
        public readonly array $evidence,
        public readonly ?CarbonInterface $nextCheckAt,
        public readonly int $weight = 0,
    ) {}

    public static function pass(string $id, array $evidence = []): self
    {
        return new self($id, self::PASS, null, '', $evidence, null);
    }

    public static function warn(string $id, array $evidence = [], int $weight = 0): self
    {
        return new self($id, self::WARN, null, '', $evidence, null, $weight);
    }

    public static function fail(string $id, string $outcome, string $reason, array $evidence = [], ?CarbonInterface $nextCheckAt = null): self
    {
        return new self($id, self::FAIL, $outcome, $reason, $evidence, $nextCheckAt);
    }

    public function failed(): bool
    {
        return $this->result === self::FAIL;
    }

    /** Stored in payout_decisions.rules. */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'result' => $this->result,
            'weight' => $this->weight,
            'outcome' => $this->outcome,
            'reason' => $this->reason ?: null,
            'evidence' => $this->evidence,
        ], fn ($v) => $v !== null);
    }
}
