<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\Updater\WhiteLabelActivationClient;
use App\Services\Updater\WhiteLabelUpdateClient;
use App\Support\Activation\LicenseState;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > Licence activation. The screen that connects this install to the original platform: enter the licence key once, and the install registers
 * itself (brand, domain, version), receives its feature list and then reports in on a schedule so the platform's oversight list always shows it.
 */
#[Layout('components.layouts.admin')]
class LicenseActivation extends Component
{
    public string $licenseKey = '';

    public string $platformUrl = '';

    public string $contactEmail = '';

    public ?string $notice = null;

    public ?string $error = null;

    public bool $confirmDisconnect = false;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->platformUrl = LicenseState::platformUrl();
        $this->contactEmail = (string) (auth()->user()->email ?? '');
    }

    private function authorizeAdmin(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(['admin', 'super_admin']), 403);
    }

    public function activate(WhiteLabelActivationClient $client): void
    {
        $this->authorizeAdmin();
        $this->notice = $this->error = null;
        $this->validate([
            'licenseKey' => ['required', 'string', 'max:64'],
            'platformUrl' => ['required', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $r = $client->activate($this->licenseKey, $this->platformUrl, $this->contactEmail ?: null);
        if (! $r['ok']) {
            $this->error = $r['error'];

            return;
        }
        $this->licenseKey = '';
        $this->notice = 'Activated. This install is registered and its licence is live.';
    }

    public function checkNow(WhiteLabelUpdateClient $client): void
    {
        $this->authorizeAdmin();
        $this->notice = $this->error = null;
        $r = $client->refreshEntitlement();
        $r['ok'] ? $this->notice = 'Checked in. The platform now shows this install as live on '.LicenseState::domain().'.' : $this->error = $r['error'];
    }

    public function disconnect(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        LicenseState::clear();
        FeatureEntitlements::store([]);
        Auditor::log('white_label.disconnected', Setting::class, null, []);
        $this->confirmDisconnect = false;
        $this->notice = 'This install is disconnected. Activate again with a licence key to reconnect.';
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        $at = fn (string $k) => ($v = (string) LicenseState::get($k, '')) !== '' ? \Illuminate\Support\Carbon::parse($v) : null;

        return [
            'status' => LicenseState::status(),
            'tier' => (string) LicenseState::get('tier', ''),
            'reference' => (string) LicenseState::get('reference', ''),
            'keyLast4' => (string) LicenseState::get('key_last4', ''),
            'domain' => (string) LicenseState::get('domain', '') ?: LicenseState::domain(),
            'liveDomain' => LicenseState::domain(),
            'activatedAt' => $at('activated_at'),
            'lastCheckin' => $at('last_checkin_at'),
            'lastAttempt' => $at('last_attempt_at'),
            'lastError' => (string) LicenseState::get('last_error', ''),
            'platform' => LicenseState::platformUrl(),
            'viaEnv' => (string) LicenseState::get('token', '') === '' && LicenseState::isActivated(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.license-activation', [
            's' => $this->state(),
            'locks' => FeatureEntitlements::all(),
            'isSuper' => (bool) auth()->user()?->hasRole('super_admin'),
        ]);
    }
}
