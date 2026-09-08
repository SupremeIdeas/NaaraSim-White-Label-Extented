<?php

namespace Tests\Support;

use App\Services\eSIM\EsimProviderInterface;
use RuntimeException;

/**
 * Configurable eSIM provider double for ProviderRouter tests. Records whether
 * orderBundle was called (to distinguish a "skipped" provider from a "failed"
 * one) and can be told to succeed or throw.
 */
class FakeEsimProvider implements EsimProviderInterface
{
    public int $orderCalls = 0;

    public function __construct(
        private bool $shouldThrow = false,
        private array $orderResponse = ['status' => 'ok'],
        private array $usageResponse = [],
    ) {}

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        $this->orderCalls++;
        if ($this->shouldThrow) {
            throw new RuntimeException("provider failed for {$planId}");
        }

        return $this->orderResponse + ['planId' => $planId];
    }

    public function getCatalogue(): array
    {
        return [];
    }

    public function getEsim(string $iccid): array
    {
        return [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        if ($this->shouldThrow) {
            throw new RuntimeException("usage lookup failed for {$iccid}");
        }

        return $this->usageResponse;
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return [];
    }

    public function getBalance(): float
    {
        return 0.0;
    }
}
