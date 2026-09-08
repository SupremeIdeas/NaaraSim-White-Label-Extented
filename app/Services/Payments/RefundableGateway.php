<?php

namespace App\Services\Payments;

/**
 * A gateway that can refund a settled charge through its API (BUILD-2 §7.1).
 * Gateways that CANNOT (the crypto rails — a blockchain payment is
 * irreversible) simply do not implement this; the RefundService routes those to
 * a "manual" outcome so an admin performs the payout by hand.
 */
interface RefundableGateway
{
    /**
     * Refund (all or part of) a settled charge, identified by the original
     * top-up reference. `$context` may carry provider-specific ids captured at
     * webhook time (e.g. Stripe payment_intent, Flutterwave transaction id).
     */
    public function refund(string $reference, float $amount, string $currency, array $context = []): RefundResult;
}
