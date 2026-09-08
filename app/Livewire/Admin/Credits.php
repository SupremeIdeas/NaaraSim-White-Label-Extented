<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\CreditSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → NaaraCredits (loyalty module). Runs the whole reward economy: the
 * rate, per-task earn amounts, the checkout redemption cap, and the compliant
 * rewarded-ad (offerwall) provider. Every value is tuned so the programme stays
 * profitable — the tooltips explain how.
 */
#[Layout('components.layouts.admin')]
class Credits extends Component
{
    public bool $enabled = true;

    public $per_usd = 100;

    public $signup_bonus = 0;

    public $checkin_daily = 5;

    public $checkin_cooldown_hours = 24;

    public $first_purchase_bonus = 50;

    public $max_redeem_pct = 50;

    // Rewarded ads (offerwall)
    public bool $ads_enabled = false;

    public string $ad_provider = '';

    public string $ad_offerwall_url = '';

    public $ad_daily_cap = 20;

    public ?string $saved = null;

    public function mount(): void
    {
        $s = CreditSettings::all();
        $this->enabled = (bool) $s['enabled'];
        $this->per_usd = $s['per_usd'];
        $this->signup_bonus = $s['signup_bonus'];
        $this->checkin_daily = $s['checkin_daily'];
        $this->checkin_cooldown_hours = $s['checkin_cooldown_hours'];
        $this->first_purchase_bonus = $s['first_purchase_bonus'];
        $this->max_redeem_pct = $s['max_redeem_pct'];
        $this->ads_enabled = (bool) $s['ads_enabled'];
        $this->ad_provider = $s['ad_provider'];
        $this->ad_offerwall_url = $s['ad_offerwall_url'];
        $this->ad_daily_cap = $s['ad_daily_cap'];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'per_usd' => 'required|integer|min:1|max:100000',
            'signup_bonus' => 'required|integer|min:0|max:1000000',
            'checkin_daily' => 'required|integer|min:0|max:1000000',
            'checkin_cooldown_hours' => 'required|integer|min:1|max:168',
            'first_purchase_bonus' => 'required|integer|min:0|max:1000000',
            'max_redeem_pct' => 'required|integer|min:0|max:100',
            'ad_provider' => 'nullable|string|max:60',
            'ad_offerwall_url' => 'nullable|url|max:500',
            'ad_daily_cap' => 'required|integer|min:0|max:1000000',
        ]);

        foreach ([
            'credits.enabled' => $this->enabled,
            'credits.per_usd' => (int) $this->per_usd,
            'credits.signup_bonus' => (int) $this->signup_bonus,
            'credits.checkin_daily' => (int) $this->checkin_daily,
            'credits.checkin_cooldown_hours' => (int) $this->checkin_cooldown_hours,
            'credits.first_purchase_bonus' => (int) $this->first_purchase_bonus,
            'credits.max_redeem_pct' => (int) $this->max_redeem_pct,
            'credits.ads_enabled' => $this->ads_enabled,
            'credits.ad_provider' => trim($this->ad_provider),
            'credits.ad_offerwall_url' => trim($this->ad_offerwall_url),
            'credits.ad_daily_cap' => (int) $this->ad_daily_cap,
        ] as $key => $value) {
            Setting::setValue($key, $value, 'credits');
        }

        CreditSettings::flush();
        Auditor::log('credits.settings_updated', null, null, ['enabled' => $this->enabled, 'per_usd' => $this->per_usd]);
        $this->saved = 'NaaraCredits settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'NaaraCredits settings saved.');
    }

    public function render()
    {
        return view('livewire.admin.credits', [
            'postbackConfigured' => filled(config('services.offerwall.postback_secret')),
            'postbackUrl' => route('webhooks.offerwall'),
        ]);
    }
}
