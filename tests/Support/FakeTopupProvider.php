<?php

namespace Tests\Support;

use App\Services\Topup\Contracts\TopupProviderInterface;
use App\Services\Topup\DTO\PurchaseRequest;
use App\Services\Topup\DTO\PurchaseResult;
use App\Services\Topup\DTO\RecipientInfo;
use Illuminate\Http\Request;

/** A scriptable supplier: queue answers for purchase()/requery(), and inspect every call it received. */
class FakeTopupProvider implements TopupProviderInterface
{
    public array $purchases = [];

    public array $requeries = [];

    /** @var list<PurchaseResult> */
    public array $purchaseScript = [];

    /** @var list<PurchaseResult> */
    public array $requeryScript = [];

    public array $products = [];

    public float $float = 1000;

    public static int $seq = 0;

    public function __construct(public string $code = 'zendit', public bool $configured = true) {}

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return ucfirst($this->code);
    }

    public function newReference(): string
    {
        return $this->code.'-ref-'.(++self::$seq);
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function lookupRecipient(string $e164Phone, ?string $countryIso = null): ?RecipientInfo
    {
        return null;
    }

    public function countries(): array
    {
        return ['NG'];
    }

    public function listProducts(string $countryIso): array
    {
        return $this->products;
    }

    public function purchase(PurchaseRequest $req): PurchaseResult
    {
        $this->purchases[] = $req;

        return array_shift($this->purchaseScript) ?? new PurchaseResult(PurchaseResult::DELIVERED, $req->reference, 'txn-'.$req->reference);
    }

    public function requery(string $reference, ?string $providerTxnId = null): PurchaseResult
    {
        $this->requeries[] = $reference;

        return array_shift($this->requeryScript) ?? new PurchaseResult(PurchaseResult::PENDING, $reference);
    }

    public function balance(): array
    {
        return ['amount' => $this->float, 'currency' => 'USD'];
    }

    public function parseWebhook(Request $request): ?PurchaseResult
    {
        if ($request->header('X-Webhook-Secret') !== 'good') {
            return null;
        }

        return new PurchaseResult((string) $request->input('status', 'delivered'), (string) $request->input('reference'));
    }
}
