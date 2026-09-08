<?php

namespace App\Http\Controllers;

use App\Models\MerchantInvoice;
use App\Services\Merchants\MerchantInvoiceService;

/**
 * Public, unauthenticated read-only invoice view — the link a merchant forwards
 * to their client over WhatsApp/email. No login exists for a merchant's client,
 * so a random 32-char token is the only guard; the page shows only what the
 * client needs to pay the merchant (never any NaaraSim wallet/cost data).
 */
class PublicInvoiceController extends Controller
{
    public function show(string $token, MerchantInvoiceService $invoices)
    {
        $invoice = MerchantInvoice::where('public_token', $token)
            ->where('status', '!=', MerchantInvoice::DRAFT)
            ->with(['merchant', 'client'])
            ->firstOrFail();

        $invoices->recordView($invoice);

        return view('invoices.public', ['invoice' => $invoice]);
    }
}
