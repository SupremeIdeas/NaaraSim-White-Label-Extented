<?php

namespace App\Services\Topup\DTO;

final class ProductDTO
{
    public function __construct(
        public string $providerSku,
        public string $countryIso,
        public string $operatorName,
        public string $type,                 // airtime | data | combo
        public string $name,
        public string $currency,             // currency the recipient receives (the "send" side)
        public bool $isFixed = true,
        public ?float $fixedAmount = null,   // send currency
        public ?float $minAmount = null,
        public ?float $maxAmount = null,
        public ?string $operatorCode = null,
        public ?string $validityText = null,
        public ?int $dataMb = null,
        public bool $dataUnlimited = false,
        public ?float $costNative = null,    // PRIVATE: our cost of a fixed product, in $costCurrency
        public ?float $costRatio = null,     // PRIVATE: our cost per 1 send unit (range products), in $costCurrency
        public ?string $costCurrency = null,
        public array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toRow(string $provider): array
    {
        return [
            'provider_code' => $provider, 'provider_sku' => $this->providerSku, 'country_iso' => strtoupper($this->countryIso),
            'operator_name' => $this->operatorName, 'operator_code' => $this->operatorCode, 'type' => $this->type, 'name' => $this->name,
            'validity_text' => $this->validityText, 'data_mb' => $this->dataMb, 'data_unlimited' => $this->dataUnlimited,
            'is_fixed' => $this->isFixed, 'min_amount' => $this->minAmount, 'max_amount' => $this->maxAmount, 'fixed_amount' => $this->fixedAmount,
            'currency' => strtoupper($this->currency), 'cost_native' => $this->costNative, 'cost_ratio' => $this->costRatio,
            'cost_currency' => $this->costCurrency ? strtoupper($this->costCurrency) : null, 'active' => true, 'synced_at' => now(), 'meta' => $this->meta ?: null,
        ];
    }
}
