<?php

namespace App\Services\Topup\DTO;

final class RecipientInfo
{
    public function __construct(
        public string $countryIso,
        public ?string $operatorName = null,
        public ?string $operatorCode = null,
        public string $source = 'provider',
    ) {}
}
