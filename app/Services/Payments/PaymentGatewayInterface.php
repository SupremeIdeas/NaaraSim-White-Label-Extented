<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Contract for wallet-funding gateways (Flutterwave, Paystack, Stripe —
 * blueprint Section 14.2). Bound as pay.flutterwave / pay.paystack / pay.stripe.
 *
 * Money-safety (19.3): verifySignature() must pass BEFORE parseWebhook() is
 * trusted; the webhook is then processed in a queued job idempotent on the
 * provider reference.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Start a wallet top-up. Returns the gateway reference and the redirect
     * URL the user is sent to.
     *
     * @return array{reference: string, redirect_url: string}
     */
    public function initialize(User $user, float $amount, string $currency, array $meta = []): array;

    /** Verify the webhook signature (constant-time) before touching the body. */
    public function verifySignature(Request $request): bool;

    /** Parse a (verified) webhook into a normalised event. */
    public function parseWebhook(Request $request): ?PaymentEvent;
}
