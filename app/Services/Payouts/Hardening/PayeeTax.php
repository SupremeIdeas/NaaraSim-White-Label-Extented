<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayeeTaxProfile;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\PayoutSettings;

/**
 * Tax-reporting HOOKS only (Addendum D-3.18). The platform is a US LLC paying people worldwide; whether and
 * what a payee must file is a question for the owner's tax professional, not code. This provides: a profile
 * table, an optional gate (`payouts.tax_form_required_over_usd`, OFF by default) and the yearly export. Product
 * copy must never claim tax compliance.
 */
class PayeeTax
{
    public function paidThisYearUsd(User $user): float
    {
        return (float) PayoutRequest::query()->where('user_id', $user->id)->where('status', PayoutRequest::PAID)
            ->whereYear('settled_at', now()->year)
            ->selectRaw("COALESCE(SUM(COALESCE(usd_amount, CASE WHEN source_bucket <> 'referral_credits' THEN credit_amount END, CASE WHEN currency = 'USD' THEN amount END, 0)), 0) AS t")
            ->value('t');
    }

    /** Is tax information required before another payout? Always false while the gate is OFF. */
    public function blocks(User $user): bool
    {
        $limit = PayoutSettings::taxFormOverUsd();
        if ($limit <= 0 || $this->paidThisYearUsd($user) < $limit) {
            return false;
        }
        $p = PayeeTaxProfile::where('user_id', $user->id)->first();

        return ! ($p && ($p->form_status === 'received' || $p->provider_collected));
    }

    /** @return list<array<string,mixed>> one row per payee for a calendar year, for the accountant */
    public function annualSummary(int $year): array
    {
        $rows = PayoutRequest::query()->where('status', PayoutRequest::PAID)->whereYear('settled_at', $year)->get();
        $by = [];
        foreach ($rows as $r) {
            $usd = (float) ($r->usd_amount ?? ($r->source_bucket !== 'referral_credits' ? $r->credit_amount : ($r->currency === 'USD' ? $r->amount : 0)));
            $key = $r->user_id.'|'.$r->provider;
            $by[$key] ??= ['user_id' => $r->user_id, 'country' => $r->account?->country, 'provider' => $r->provider, 'payouts' => 0, 'total_usd' => 0.0];
            $by[$key]['payouts']++;
            $by[$key]['total_usd'] = round($by[$key]['total_usd'] + $usd, 4);
        }
        $profiles = PayeeTaxProfile::whereIn('user_id', array_unique(array_column($by, 'user_id')))->get()->keyBy('user_id');

        return array_values(array_map(function ($row) use ($profiles) {
            $p = $profiles->get($row['user_id']);

            return $row + ['tax_country' => $p?->tax_country, 'form_type' => $p?->form_type, 'form_status' => $p?->form_status ?? 'none', 'provider_collected' => (bool) $p?->provider_collected];
        }, $by));
    }
}
