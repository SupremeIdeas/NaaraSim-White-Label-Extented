<?php

namespace App\Services\GiftCards;

/**
 * Optional capability: the provider exposes a real order/transaction-status
 * lookup we can poll (Reloadly's transaction endpoint, Bitrefill's invoice
 * endpoint, Tillo's check-order-status-by-reference). Implemented only where
 * that's actually documented — Zendit's async completion currently relies on
 * its webhook alone, so it does NOT implement this rather than fake a lookup
 * that doesn't exist.
 *
 * Used by the `giftcards:reconcile-processing` command to finalize an order
 * that's been stuck in 'processing' past a threshold because a webhook never
 * arrived.
 */
interface GiftCardStatusCheckable
{
    /**
     * @return array{status: 'processing'|'delivered'|'failed', receipt: array<string, mixed>}
     */
    public function orderStatus(string $providerTxId): array;
}
