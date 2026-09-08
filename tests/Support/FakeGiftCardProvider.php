<?php

namespace Tests\Support;

use App\Services\GiftCards\GiftCardProviderException;
use App\Services\GiftCards\GiftCardProviderInterface;
use App\Services\GiftCards\GiftCardStatusCheckable;

/**
 * Configurable Naara Gift provider double. Counts order() calls (to prove the
 * double-submit guard orders exactly once) and can be told to deliver, return a
 * non-throwing 'failed'/'processing' status, or throw — so the money-path guards
 * (refund-on-failure, no double charge) are exercised deterministically.
 * Also implements GiftCardStatusCheckable (freely, as a test double — this
 * doesn't claim any real provider has this capability, unlike the production
 * services which only implement it where actually documented) so the
 * reconcile command can be tested against a controllable status response.
 */
class FakeGiftCardProvider implements GiftCardProviderInterface, GiftCardStatusCheckable
{
    public int $orderCalls = 0;

    public array $statusResponse = ['status' => 'processing', 'receipt' => []];

    public function __construct(
        private string $status = 'delivered',
        private array $receipt = ['code' => 'GIFT-1234', 'epin' => 'PIN-9'],
        private bool $shouldThrow = false,
    ) {}

    public function orderStatus(string $providerTxId): array
    {
        return $this->statusResponse;
    }

    public function key(): string
    {
        return 'reloadly';
    }

    public function available(): bool
    {
        return true;
    }

    public function getCatalogue(): array
    {
        return [];
    }

    public function getBalance(): float
    {
        return 0.0;
    }

    public function preflight(): array
    {
        return ['ok' => true, 'balance' => 100.0, 'currency' => 'USD', 'products' => 5, 'error' => null];
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        $this->orderCalls++;
        if ($this->shouldThrow) {
            throw new GiftCardProviderException('provider down');
        }

        return ['provider_tx_id' => 'tx-'.$reference, 'status' => $this->status, 'receipt' => $this->receipt];
    }
}
