<?php

namespace App\Services\Topup\DTO;

final class PurchaseRequest
{
    public function __construct(
        public string $reference,          // our unique reference, stored on the order BEFORE the call
        public string $phoneE164,
        public string $countryIso,
        public string $productType,        // airtime | data | combo
        public string $providerSku,
        public float $amount,              // send currency (what the recipient receives)
        public string $currency,
        public bool $isFixed = true,
        public ?string $operatorCode = null,
        public ?string $senderPhone = null,
        public ?string $senderEmail = null,
        public array $meta = [],
    ) {}
}
