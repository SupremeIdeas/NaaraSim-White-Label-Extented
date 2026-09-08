<?php

namespace App\Livewire\Admin;

use App\Models\ApiClient;
use App\Models\Setting;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Developer API (ROADMAP §Layer 2). The operator switches the whole
 * Developer API program on/off and sets the developer markups (wholesale + this
 * %). The markups only ever set the DISCOUNT developers get; MarginGuard still
 * floors every developer price at cost + minimum profit, so no value entered
 * here — even 0% — can ever sell below cost. Also lists every API client for
 * oversight (never provider cost).
 */
#[Layout('components.layouts.admin')]
class DeveloperApi extends Component
{
    public bool $enabled = false;

    public $esimMarkup = 10;

    public $smsMarkup = 15;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->enabled = (bool) Setting::getValue('developer_api.enabled', false);
        $this->esimMarkup = (float) Setting::getValue('pricing.developer_markup_pct', 10);
        $this->smsMarkup = (float) Setting::getValue('pricing.developer_sms_markup_pct', 15);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'esimMarkup' => 'required|numeric|min:0|max:500',
            'smsMarkup' => 'required|numeric|min:0|max:500',
        ]);

        Setting::setValue('developer_api.enabled', $this->enabled, 'developer_api');
        Setting::setValue('pricing.developer_markup_pct', (float) $this->esimMarkup, 'pricing');
        Setting::setValue('pricing.developer_sms_markup_pct', (float) $this->smsMarkup, 'pricing');

        Auditor::log('developer_api.settings_updated', null, null, [
            'enabled' => $this->enabled,
            'esim_markup_pct' => $this->esimMarkup,
            'sms_markup_pct' => $this->smsMarkup,
        ]);

        $this->saved = 'Developer API settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Developer API settings saved.');
    }

    public function render()
    {
        $clients = ApiClient::with('owner:id,name,email')->latest()->get();

        return view('livewire.admin.developer-api', [
            'clients' => $clients,
            'liveCount' => $clients->where('is_active', true)->count(),
            'totalBalance' => (float) $clients->sum('prepaid_balance_usd'),
        ]);
    }
}
