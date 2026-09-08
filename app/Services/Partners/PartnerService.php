<?php

namespace App\Services\Partners;

use App\Models\Partner;
use App\Models\User;
use App\Support\Auditor;
use App\Support\PartnerSettings;

/**
 * Partner lifecycle (admin-owned). A partner is always created BY an admin
 * (promote an existing user) — there is no self-signup — and their profit-share
 * percentage is admin-set, never self-selected. Every action is audited.
 */
class PartnerService
{
    /** Promote a user to a partner (idempotent — returns the existing row). */
    public function create(User $owner, float $sharePct, ?string $cadence = null, ?string $mode = null): Partner
    {
        $existing = Partner::where('owner_user_id', $owner->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $partner = Partner::create([
            'owner_user_id' => $owner->id,
            'status' => Partner::ACTIVE,
            'profit_share_pct' => $this->clampShare($sharePct),
            'payout_cadence' => in_array($cadence, [Partner::CADENCE_WEEKLY, Partner::CADENCE_MONTHLY], true) ? $cadence : PartnerSettings::defaultCadence(),
            'payout_mode' => in_array($mode, [Partner::MODE_MANUAL, Partner::MODE_AUTO], true) ? $mode : PartnerSettings::defaultMode(),
        ]);
        Auditor::log('partners.created', 'Partner', $partner->id, ['owner' => $owner->id]);

        return $partner;
    }

    /** Update the admin-owned terms (share %, cadence, payout mode). */
    public function updateTerms(Partner $partner, float $sharePct, string $cadence, string $mode): Partner
    {
        $partner->forceFill([
            'profit_share_pct' => $this->clampShare($sharePct),
            'payout_cadence' => in_array($cadence, [Partner::CADENCE_WEEKLY, Partner::CADENCE_MONTHLY], true) ? $cadence : $partner->payout_cadence,
            'payout_mode' => in_array($mode, [Partner::MODE_MANUAL, Partner::MODE_AUTO], true) ? $mode : $partner->payout_mode,
        ])->save();
        Auditor::log('partners.terms_updated', 'Partner', $partner->id);

        return $partner;
    }

    public function activate(Partner $partner): void
    {
        $partner->forceFill(['status' => Partner::ACTIVE, 'reason' => null])->save();
        Auditor::log('partners.activated', 'Partner', $partner->id);
    }

    public function suspend(Partner $partner, ?string $reason = null): void
    {
        $partner->forceFill(['status' => Partner::SUSPENDED, 'reason' => $reason])->save();
        Auditor::log('partners.suspended', 'Partner', $partner->id, ['reason' => $reason]);
    }

    /** Remove the partner. Their earnings ledger is retained (cascade only on hard delete). */
    public function remove(Partner $partner): void
    {
        $id = $partner->id;
        $partner->delete();
        Auditor::log('partners.removed', 'Partner', $id);
    }

    private function clampShare(float $pct): float
    {
        return round(max(0.0, min(100.0, $pct)), 3);
    }
}
