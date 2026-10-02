<?php

namespace App\Services\Payouts;

/**
 * Every payout gateway states what it can and cannot do, so the engine and the tests never assume
 * (Addendum D, C7/C8). A gateway that cannot confirm by webhook or look a transfer up is still allowed — the
 * engine then routes unknown outcomes to a human — but it must SAY so.
 */
interface DeclaresCapabilities
{
    /**
     * @return array{confirms_synchronously: bool, webhook: bool, lookup: bool, cancel: bool}
     *   confirms_synchronously  a successful send is already final (`paid`), e.g. a Stripe Transfer
     *   webhook                 the provider calls us back with the final state
     *   lookup                  we can ask the provider about a transfer by our reference
     *   cancel                  an in-flight transfer can be cancelled at the provider
     */
    public function capabilities(): array;
}
