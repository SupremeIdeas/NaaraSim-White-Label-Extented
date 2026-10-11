<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutRequest;
use App\Services\Payouts\FloatService;

/**
 * Gate G9 against the real float: the balance must cover this payout PLUS everything
 * older that is approved/waiting and has not drawn it yet (first come, first served).
 * A rail without float tracking is "not tracked" and passes, as before.
 */
class FloatFundingChecker implements FundingChecker
{
    public function __construct(private FloatService $float) {}

    public function check(PayoutRequest $request): array
    {
        $row = $this->float->tracked((string) $request->provider, $request->currency);
        if ($row === null) {
            return ['status' => 'not_tracked', 'evidence' => ['note' => 'float not tracked for this rail']];
        }

        $needed = (float) $request->amount + $this->float->committedAhead($request);
        $evidence = ['float' => (float) $row->balance, 'needed_incl_queue_ahead' => round($needed, 4)];

        return ['status' => (float) $row->balance >= $needed ? 'ok' : 'short', 'evidence' => $evidence];
    }
}
