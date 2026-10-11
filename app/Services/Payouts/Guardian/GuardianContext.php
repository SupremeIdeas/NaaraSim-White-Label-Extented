<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutRequest;
use App\Models\User;

/** Everything a gate needs about one request, loaded once. Read-only. */
final class GuardianContext
{
    public readonly ?User $user;

    public readonly ?PayoutAccount $account;

    public readonly ?PayoutCorridor $corridor;

    public function __construct(public readonly PayoutRequest $request)
    {
        $this->user = $request->user;
        $this->account = $request->account;
        $this->corridor = $request->corridor_id ? PayoutCorridor::find($request->corridor_id) : null;
    }

    /** The USD value of this payout, or null when it cannot be established (legacy rows). */
    public function usd(): ?float
    {
        if ($this->request->usd_amount !== null) {
            return (float) $this->request->usd_amount;
        }
        // Earnings buckets store the USD held in credit_amount.
        if ($this->request->source_bucket !== 'referral_credits' && $this->request->credit_amount !== null) {
            return (float) $this->request->credit_amount;
        }

        return null;
    }
}
