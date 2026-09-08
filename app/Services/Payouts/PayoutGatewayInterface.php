<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use Illuminate\Http\Request;

/**
 * Contract for the money-OUT side of a PSP (ROADMAP §Layer 0.2), mirroring
 * PaymentGatewayInterface. One implementation per PSP, resolved by name via
 * app("payout.$provider"). Money-safety: verifyWebhook() must pass BEFORE
 * parseWebhook() is trusted; the final ledger state comes from the webhook,
 * never the synchronous send response alone.
 */
interface PayoutGatewayInterface
{
    public function name(): string;

    /** True once the PSP's key is configured. */
    public function available(): bool;

    /**
     * Ensure a PSP transfer-recipient exists for the account, returning its ref
     * (cached on the account by the engine so it's created only once).
     */
    public function createRecipient(PayoutAccount $account): string;

    /** Send the transfer. Never throws for a normal PSP rejection — returns a
     *  `failed` result instead; only infra errors bubble up. */
    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult;

    /** Verify the payout webhook signature (constant-time) before touching it. */
    public function verifyWebhook(Request $request): bool;

    /** Parse a (verified) payout webhook into a normalised event. */
    public function parseWebhook(Request $request): ?PayoutEvent;
}
