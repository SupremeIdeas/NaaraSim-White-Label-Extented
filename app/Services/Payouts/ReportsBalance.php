<?php

namespace App\Services\Payouts;

/**
 * Optional capability: a gateway that can report the platform's own balance at that
 * provider, so tracked float can be reconciled against the provider's truth.
 */
interface ReportsBalance
{
    /** @return array<string, float> major-unit balance per ISO currency, e.g. ['NGN' => 1500000.0] */
    public function balances(): array;
}
