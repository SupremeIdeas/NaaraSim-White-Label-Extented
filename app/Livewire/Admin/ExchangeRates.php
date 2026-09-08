<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\Pricing\CurrencyService;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Exchange rate (NGN). The USD→NGN rate is the single authoritative
 * number used for BOTH displayed prices and payouts, so it must reflect what
 * Nigerian customers actually transact at — the parallel ("black-market") rate,
 * not the official interbank rate. This screen lets the admin either type the
 * exact rate (manual) or track Airalo's live official rate plus a parallel-market
 * markup. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class ExchangeRates extends Component
{
    /** 'auto' = Airalo live official mid-rate · 'manual' = the exact rate below. */
    public string $ngn_rate_source = 'auto';

    /** The exact USD→NGN rate used in manual mode (and as the auto fallback). */
    public float $manual_ngn_rate = 1500.0;

    /** Parallel-market premium (%) added on top of the base rate (0–50). */
    public float $ngn_rate_markup_pct = 0.0;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->ngn_rate_source = Setting::getValue('pricing.ngn_rate_source', 'auto') === 'manual' ? 'manual' : 'auto';
        $this->manual_ngn_rate = (float) Setting::getValue('pricing.manual_ngn_rate', 1500.0);
        $this->ngn_rate_markup_pct = (float) Setting::getValue('pricing.ngn_rate_markup_pct', 0.0);
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'ngn_rate_source' => 'required|in:auto,manual',
            'manual_ngn_rate' => 'required|numeric|min:100|max:100000',
            'ngn_rate_markup_pct' => 'required|numeric|min:0|max:50',
        ], [
            'manual_ngn_rate.min' => 'That NGN rate looks too low — enter the naira value of $1.',
        ]);

        Setting::setValue('pricing.ngn_rate_source', $this->ngn_rate_source, 'pricing');
        Setting::setValue('pricing.manual_ngn_rate', round((float) $this->manual_ngn_rate, 2), 'pricing');
        Setting::setValue('pricing.ngn_rate_markup_pct', round(max(0, min(50, (float) $this->ngn_rate_markup_pct)), 2), 'pricing');

        app(CurrencyService::class)->flushNgnRate();
        Auditor::log('pricing.ngn_rate_updated', payload: [
            'source' => $this->ngn_rate_source,
            'markup_pct' => $this->ngn_rate_markup_pct,
        ]);
        $this->saved = 'NGN rate saved — live across prices and payouts.';
        $this->dispatch('nx-toast', type: 'success', message: 'NGN rate saved.');
    }

    public function render()
    {
        $currency = app(CurrencyService::class);

        return view('livewire.admin.exchange-rates', [
            // The rate as it resolves RIGHT NOW (base + markup), for the live preview.
            'resolvedRate' => $currency->getUsdToNgn(),
        ]);
    }
}
