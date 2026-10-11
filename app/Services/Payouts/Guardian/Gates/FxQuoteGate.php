<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;
use App\Services\Payouts\PayoutException;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutSettings;

/**
 * G8 — the locked quote is still honourable. Inside its window: pass. Expired: the
 * platform absorbs drift up to the tolerance (warn); beyond it a human decides.
 * The Guardian never silently changes what the user was promised.
 */
class FxQuoteGate implements Gate
{
    public function __construct(private CurrencyService $fx) {}

    public function id(): string
    {
        return 'G8_fx_quote';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $r = $ctx->request;
        if ($r->fx_rate === null || $r->quote_expires_at === null) {
            return GateResult::pass($this->id(), ['note' => 'legacy request, no locked quote']);
        }
        if ($r->quote_expires_at->isFuture()) {
            return GateResult::pass($this->id(), ['quote_valid_until' => $r->quote_expires_at->toIso8601String()]);
        }

        try {
            $current = $this->fx->usdTo($r->currency);
        } catch (PayoutException) {
            return GateResult::fail($this->id(), GateResult::DEFER, 'fx_unavailable', ['currency' => $r->currency], now()->addMinutes(10));
        }

        $locked = (float) $r->fx_rate;
        $driftPct = $locked > 0 ? abs($current - $locked) / $locked * 100 : 100.0;
        $evidence = ['locked' => $locked, 'current' => $current, 'drift_pct' => round($driftPct, 2), 'tolerance_pct' => PayoutSettings::fxTolerancePct()];

        return $driftPct <= PayoutSettings::fxTolerancePct()
            ? GateResult::warn($this->id(), $evidence + ['note' => 'quote expired; platform absorbs the drift'])
            : GateResult::fail($this->id(), GateResult::HOLD, 'fx_drift', $evidence);
    }
}
