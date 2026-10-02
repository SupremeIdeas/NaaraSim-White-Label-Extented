<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Models\PayoutAccountFingerprint;
use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;

/** G6 — one destination shared by several users is a farming signal. Same user reusing it is fine. */
class DestinationSharingGate implements Gate
{
    public function id(): string
    {
        return 'G6_destination_sharing';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $account = $ctx->account;
        if ($account === null || blank($account->lookup_hash)) {
            return GateResult::pass($this->id(), ['note' => 'no fingerprint']);
        }

        $others = PayoutAccountFingerprint::where('provider', $account->provider)
            ->where('fingerprint', $account->lookup_hash)
            ->where('user_id', '!=', $ctx->request->user_id)
            ->pluck('user_id');

        return $others->isEmpty()
            ? GateResult::pass($this->id())
            : GateResult::fail($this->id(), GateResult::HOLD, 'destination_shared', ['other_user_ids' => $others->unique()->values()->all()]);
    }
}
