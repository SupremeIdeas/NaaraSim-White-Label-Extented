<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Services\Payouts\Guardian\HoldCheck;
use App\Services\Payouts\Guardian\HoldVerifier;

/** G3 — "is this real": the source ledger holds exactly this request's money. */
class LedgerHoldGate implements Gate
{
    public function __construct(private HoldVerifier $verifier) {}

    public function id(): string
    {
        return 'G3_ledger_hold';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $check = $this->verifier->verify($ctx->request);

        return $check->ok()
            ? GateResult::pass($this->id(), $check->evidence)
            : GateResult::fail($this->id(), GateResult::HOLD,
                $check->status === HoldCheck::UNVERIFIABLE ? 'hold_unverifiable' : 'hold_mismatch',
                ['status' => $check->status] + $check->evidence);
    }
}
