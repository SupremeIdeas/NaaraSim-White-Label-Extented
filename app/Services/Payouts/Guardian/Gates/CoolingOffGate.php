<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Models\AuditLog;
use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Support\PayoutSettings;

/**
 * G5 — no payout within N hours of a payout-account change, password change or 2FA
 * change (account-takeover defence). A pure DEFER: it resumes by itself.
 */
class CoolingOffGate implements Gate
{
    private const EVENTS = ['account.password_changed', 'account.2fa_enabled', 'account.2fa_disabled', 'payout.account_added'];

    public function id(): string
    {
        return 'G5_cooling_off';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $hours = PayoutSettings::coolingOffHours();
        if ($hours === 0 || $ctx->user === null) {
            return GateResult::pass($this->id(), ['hours' => $hours]);
        }

        $since = now()->subHours($hours);
        $last = AuditLog::where('user_id', $ctx->user->id)->whereIn('action', self::EVENTS)
            ->where('created_at', '>=', $since)->latest('created_at')->first();

        $latest = $last?->created_at;
        $accountAt = $ctx->account?->created_at;
        if ($accountAt !== null && $accountAt->gte($since) && ($latest === null || $accountAt->gt($latest))) {
            $latest = $accountAt;
            $event = 'payout_account_added';
        }

        if ($latest === null) {
            return GateResult::pass($this->id(), ['hours' => $hours]);
        }

        return GateResult::fail($this->id(), GateResult::DEFER, 'cooling_off',
            ['hours' => $hours, 'event' => $event ?? $last?->action, 'event_at' => $latest->toIso8601String()],
            $latest->copy()->addHours($hours));
    }
}
