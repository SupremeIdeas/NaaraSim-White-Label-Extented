<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;

/** One read-only Guardian rule. No external HTTP, no writes. */
interface Gate
{
    public function id(): string;

    public function check(GuardianContext $ctx): GateResult;
}
