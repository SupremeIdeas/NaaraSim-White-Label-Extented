<?php

namespace App\Services\Merchants;

use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantClientSubscription;
use App\Models\MerchantInvoice;
use App\Support\Auditor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Merchant V2 invoice bookkeeping. A client never logs in or pays through
 * NaaraSim — this only tracks what a merchant billed a client and whether the
 * merchant has marked it settled. Nothing here calls WalletService.
 */
class MerchantInvoiceService
{
    public function create(
        Merchant $merchant,
        MerchantClient $client,
        string $description,
        float $amount,
        ?Carbon $dueAt = null,
        ?MerchantClientSubscription $subscription = null,
    ): MerchantInvoice {
        if ($amount <= 0) {
            throw new MerchantException('Invoice amount must be greater than zero.');
        }

        return MerchantInvoice::create([
            'merchant_id' => $merchant->id,
            'merchant_client_id' => $client->id,
            'merchant_client_subscription_id' => $subscription?->id,
            'description' => $description,
            'amount' => round($amount, 2),
            'status' => MerchantInvoice::DRAFT,
            'due_at' => $dueAt,
            'reference' => 'inv:'.$merchant->id.':'.Str::uuid(),
            'public_token' => Str::random(32),
        ]);
    }

    /** Mark a draft invoice sent (idempotent — sending twice does not reset sent_at). */
    public function send(MerchantInvoice $invoice): MerchantInvoice
    {
        if ($invoice->status === MerchantInvoice::DRAFT) {
            $invoice->forceFill(['status' => MerchantInvoice::SENT, 'sent_at' => now()])->save();
            Auditor::log('merchant.invoice_sent', 'MerchantInvoice', $invoice->id);
        }

        return $invoice;
    }

    /** Record the client opening the public invoice link (best-effort, never blocks the view). */
    public function recordView(MerchantInvoice $invoice): void
    {
        if ($invoice->viewed_at === null) {
            $invoice->forceFill(['viewed_at' => now()])->save();
        }
    }

    public function markPaid(MerchantInvoice $invoice): MerchantInvoice
    {
        if ($invoice->status === MerchantInvoice::VOID) {
            throw new MerchantException('A voided invoice cannot be marked paid.');
        }
        $invoice->forceFill(['status' => MerchantInvoice::PAID, 'paid_at' => now()])->save();
        Auditor::log('merchant.invoice_paid', 'MerchantInvoice', $invoice->id);

        return $invoice;
    }

    public function void(MerchantInvoice $invoice): MerchantInvoice
    {
        if ($invoice->status === MerchantInvoice::PAID) {
            throw new MerchantException('A paid invoice cannot be voided.');
        }
        $invoice->forceFill(['status' => MerchantInvoice::VOID])->save();
        Auditor::log('merchant.invoice_voided', 'MerchantInvoice', $invoice->id);

        return $invoice;
    }
}
