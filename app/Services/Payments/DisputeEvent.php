<?php

namespace App\Services\Payments;

/**
 * Normalised dispute/chargeback event (BUILD-2 §7.2), produced by a gateway
 * after its signature has been verified. `status`:
 *   open  — a dispute was logged; freeze the funds
 *   won   — resolved in our favour; unfreeze
 *   lost  — charged back; unfreeze then debit (the money is pulled)
 * `reference` is our original top-up reference (used to find the user + charge).
 */
class DisputeEvent
{
    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    public function __construct(
        public readonly string $gateway,
        public readonly string $providerDisputeId,
        public readonly ?string $reference,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly array $raw = [],
        // The provider's charge id cited by the dispute (Stripe payment_intent,
        // PayPal capture id, …) — used to map back to our reference when the
        // dispute payload doesn't carry it directly.
        public readonly string $providerChargeId = '',
    ) {}
}
