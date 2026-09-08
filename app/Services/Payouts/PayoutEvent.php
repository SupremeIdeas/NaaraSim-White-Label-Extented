<?php

namespace App\Services\Payouts;

/**
 * A normalised payout webhook event (mirrors Payments\PaymentEvent). Only the
 * final states matter to the ledger: paid / failed / reversed.
 */
final class PayoutEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly string $reference,      // our payout_requests.reference
        public readonly string $status,         // paid | failed | reversed
        public readonly ?string $providerRef = null,
    ) {
    }
}
