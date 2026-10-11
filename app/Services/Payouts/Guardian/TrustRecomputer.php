<?php

namespace App\Services\Payouts\Guardian;

use App\Models\PayoutDecision;
use App\Models\PayoutRequest;
use App\Models\PayoutTrustProfile;

/**
 * Daily: derives each payee's trust tier from their record. `new` → `trusted` after
 * enough CLEAN payouts and no recent incident; an incident knocks them back. `vip`
 * and any admin override are never touched here — only a human sets those.
 */
class TrustRecomputer
{
    public const CLEAN_FOR_TRUSTED = 3;

    private const INCIDENT_REASONS = ['hold_mismatch', 'destination_shared', 'name_mismatch', 'amount_spike'];

    private const INCIDENT_WINDOW_DAYS = 60;

    /** @return int profiles written */
    public function run(): int
    {
        $users = PayoutRequest::query()->select('user_id')->distinct()->pluck('user_id');
        $written = 0;

        foreach ($users as $userId) {
            $profile = PayoutTrustProfile::find($userId);
            if ($profile?->override_by !== null) {
                continue; // a human decided
            }

            $clean = PayoutRequest::where('user_id', $userId)->where('status', PayoutRequest::PAID)->count();
            $incidentAt = PayoutDecision::query()
                ->join('payout_requests', 'payout_requests.id', '=', 'payout_decisions.payout_request_id')
                ->where('payout_requests.user_id', $userId)->whereIn('payout_decisions.reason', self::INCIDENT_REASONS)
                ->max('payout_decisions.decided_at');
            $failedAt = PayoutRequest::where('user_id', $userId)->whereIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED])->max('updated_at');
            $last = collect([$incidentAt, $failedAt, $profile?->last_incident_at])->filter()->map(fn ($d) => \Carbon\Carbon::parse($d))->max();

            $recent = $last !== null && $last->gt(now()->subDays(self::INCIDENT_WINDOW_DAYS));
            $tier = $profile?->tier === 'vip' ? 'vip' : (($clean >= self::CLEAN_FOR_TRUSTED && ! $recent) ? 'trusted' : 'new');

            PayoutTrustProfile::updateOrCreate(['user_id' => $userId], [
                'tier' => $tier, 'clean_payouts' => $clean, 'last_incident_at' => $last, 'updated_at' => now(),
            ]);
            $written++;
        }

        return $written;
    }
}
