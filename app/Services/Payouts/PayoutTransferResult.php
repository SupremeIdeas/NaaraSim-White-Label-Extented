<?php

namespace App\Services\Payouts;

/**
 * The immediate result of asking a PSP to send a transfer. `processing` means
 * the PSP accepted it and a webhook will confirm the final state; `paid` means
 * it settled synchronously; `failed` means the PSP rejected it up front.
 */
final class PayoutTransferResult
{
    public function __construct(
        public readonly string $status,          // processing | paid | failed
        public readonly ?string $providerRef = null,
        public readonly ?string $failureReason = null,
    ) {
    }
}
