<?php

namespace App\Services\Topup\Contracts;

use App\Services\Topup\DTO\ProductDTO;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\DTO\RecipientInfo;
use Illuminate\Http\Request;

/**
 * One normalised contract for every top-up supplier. Each provider is an adapter: no supplier-specific logic lives outside
 * app/Services/Topup/Providers. Adapters map EVERY response to exactly one of four statuses (delivered | pending | failed | reversed)
 * and anything they do not recognise is `pending`, never `failed`, so an unknown answer triggers a requery instead of a refund-and-resend.
 */
interface TopupProviderInterface
{
    /** 'zendit' | 'vtpass' | 'reloadly' | 'ding' | 'bitrefill' */
    public function code(): string;

    public function name(): string;

    /** A fresh unique reference in the format this supplier demands (VTpass needs a Lagos-time prefix, Zendit a free string...). */
    public function newReference(): string;

    /** True when the keys this adapter needs are saved. */
    public function configured(): bool;

    /** Detect operator/country for a number (never from the prefix alone: numbers are portable). Null when the supplier cannot tell. */
    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo;

    /** Live, normalised airtime/data products for one country. @return ProductDTO[] */
    public function listProducts(string $countryIso): array;

    /** Countries the supplier sells in (ISO-2). Empty when it cannot say. @return string[] */
    public function countries(): array;

    /** Submit a purchase. Safe to call once per $req->reference. */
    public function purchase(PurchaseRequest $req): PurchaseResult;

    /** Current status of a previously submitted purchase. */
    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult;

    /** Supplier wallet/float, in the supplier's currency. @return array{amount: float, currency: string} */
    public function balance(): array;

    /** Verify + parse an inbound webhook. Null when it is not a (verified) status update. */
    public function parseWebhook(Request $request): ?PurchaseResult;
}
