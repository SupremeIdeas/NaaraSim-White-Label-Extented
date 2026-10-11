<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\FundingChecker;
use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;

/** G9 — the provider float covers this payout (FIFO behind older committed ones). Short → defer. */
class FundingGate implements Gate
{
    public function __construct(private FundingChecker $funding) {}

    public function id(): string
    {
        return 'G9_funding';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $res = $this->funding->check($ctx->request);

        return $res['status'] === 'short'
            ? GateResult::fail($this->id(), GateResult::DEFER, 'float_short', $res['evidence'], now()->addMinutes(5))
            : GateResult::pass($this->id(), ['status' => $res['status']] + $res['evidence']);
    }
}
