<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Support\PayoutSettings;
use Illuminate\Http\Request;

/**
 * The always-available Plan B rail (Addendum D-3.24): NO provider call is ever made. The request is handed
 * to an admin, who pays the person from the company's own bank/app and then records proof
 * (PayoutService::recordManualPayment). Until a real provider is live this lets the guide, the radar and
 * the accounting run end to end. It is a GLOBAL-rail provider, so Funding Radar counts it.
 *
 * Off by default; the owner switches it on in Admin → Payouts.
 */
class ManualExternalPayoutGateway implements PayoutGatewayInterface, DeclaresCapabilities
{
    public const NAME = 'manual_external';

    public function name(): string
    {
        return self::NAME;
    }

    public function available(): bool
    {
        return PayoutSettings::manualExternalEnabled();
    }

    public function createRecipient(PayoutAccount $account): string
    {
        return 'manual:'.$account->id; // nothing exists at any provider
    }

    /** Never moves money: reports `processing`, which parks the request until an admin records proof. */
    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        return new PayoutTransferResult(status: 'processing', providerRef: null);
    }

    public function verifyWebhook(Request $request): bool
    {
        return false; // there is no provider to call us
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        return null;
    }

    public function capabilities(): array
    {
        return ['confirms_synchronously' => false, 'webhook' => false, 'lookup' => false, 'cancel' => false];
    }
}
