<?php

namespace App\Services\Payments;

/**
 * Normalised payment webhook event. Produced by each gateway after the
 * signature has been verified, so its fields are trusted (the amount is what
 * the provider says was actually paid). `reference` is the provider_order_ref
 * used for idempotency (blueprint Section 19.3).
 */
class PaymentEvent
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $reference,
        public readonly ?int $userId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,   // success | failed | pending
        public readonly array $raw = [],
        // The provider's own charge id (Stripe payment_intent, PayPal capture id,
        // Flutterwave txn id) captured for later refunds/disputes; '' when N/A.
        public readonly string $providerChargeId = '',
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
