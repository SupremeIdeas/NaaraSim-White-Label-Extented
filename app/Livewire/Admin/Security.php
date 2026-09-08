<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SecuritySettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Security (blueprint Section 25). TOTP two-factor enrolment for the
 * admin panel: an admin without a confirmed secret is routed here and cannot
 * reach any other admin page until 2FA is confirmed. Uses Fortify's action
 * classes directly (the admin is already authenticated + role-checked).
 */
#[Layout('components.layouts.admin')]
class Security extends Component
{
    /** Show the QR/secret + confirm step after enabling. */
    public bool $showingSetup = false;

    /** TOTP code the admin reads from their authenticator app. */
    public string $code = '';

    /** Admin-toggleable site protection (super-admin only). */
    public bool $csp_enabled = true;

    public bool $hsts_enabled = true;

    /** Require TOTP 2FA to use the admin panel (opt-in extra layer). */
    public bool $admin_2fa_required = false;

    /** Cloudflare Turnstile bot challenge on login/register (opt-in). */
    public bool $turnstile_enabled = false;

    /** Panel-managed admin access control (owner request). */
    public bool $ip_allowlist_enabled = false;

    public string $ip_allowlist = '';

    public bool $country_allowlist_enabled = false;

    /** Comma/space-separated ISO-3166 alpha-2 codes, e.g. "NG, GB, US". */
    public string $allowed_countries = '';

    /** Explicit acknowledgement when the new rules would exclude the current request. */
    public bool $lockout_ack = false;

    public ?string $siteSaved = null;

    public ?string $accessSaved = null;

    public ?string $saved = null;

    public function mount(): void
    {
        $s = SecuritySettings::current();
        $this->csp_enabled = $s['csp_enabled'];
        $this->hsts_enabled = $s['hsts_enabled'];
        $this->admin_2fa_required = $s['admin_2fa_required'];
        $this->turnstile_enabled = $s['turnstile_enabled'] ?? false;
        $this->ip_allowlist_enabled = $s['admin_ip_allowlist_enabled'] ?? false;
        $this->ip_allowlist = implode("\n", $s['admin_ip_allowlist'] ?? []);
        $this->country_allowlist_enabled = $s['admin_country_allowlist_enabled'] ?? false;
        $this->allowed_countries = implode(', ', $s['admin_allowed_countries'] ?? []);
    }

    /**
     * Save the panel-managed IP + country allow-lists (super-admin only). Guards
     * against a blind self-lockout: if the new rules would exclude THIS request,
     * an explicit acknowledgement is required before saving.
     */
    public function saveAccessControl(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $ips = collect(preg_split('/[\s,]+/', $this->ip_allowlist))
            ->map(fn ($v) => trim($v))->filter()->unique()->values()->all();

        $countries = collect(preg_split('/[\s,]+/', strtoupper($this->allowed_countries)))
            ->map(fn ($v) => trim($v))->filter(fn ($v) => preg_match('/^[A-Z]{2}$/', $v))
            ->unique()->values()->all();

        // Self-lockout guard: would these rules block the request making the change?
        $request = request();
        $wouldBlockIp = $this->ip_allowlist_enabled && ! empty($ips) && ! in_array($request->ip(), $ips, true);
        $currentCountry = \App\Support\AdminAccess::currentCountry($request);
        $wouldBlockCountry = $this->country_allowlist_enabled && ! empty($countries)
            && $currentCountry !== null && ! in_array($currentCountry, $countries, true);

        if (($wouldBlockIp || $wouldBlockCountry) && ! $this->lockout_ack) {
            $this->addError('lockout_ack', 'These rules would block your current '
                .($wouldBlockIp ? 'IP' : 'country').'. Tick the box to confirm you have another way in.');

            return;
        }

        Setting::setValue('security.admin_ip_allowlist_enabled', $this->ip_allowlist_enabled, 'security');
        Setting::setValue('security.admin_ip_allowlist', $ips, 'security');
        Setting::setValue('security.admin_country_allowlist_enabled', $this->country_allowlist_enabled, 'security');
        Setting::setValue('security.admin_allowed_countries', $countries, 'security');
        SecuritySettings::flush();

        $this->allowed_countries = implode(', ', $countries);
        $this->ip_allowlist = implode("\n", $ips);
        $this->lockout_ack = false;

        Auditor::log('security.access_control_updated', null, null, [
            'ip_enabled' => $this->ip_allowlist_enabled, 'ip_count' => count($ips),
            'country_enabled' => $this->country_allowlist_enabled, 'countries' => $countries,
        ]);

        $this->accessSaved = 'Admin access rules saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Admin access rules saved.');
    }

    /**
     * Save the site-protection toggles. Super-admin only; takes effect
     * immediately (no redeploy) and is audited.
     */
    public function saveSiteProtection(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        Setting::setValue('security.csp_enabled', $this->csp_enabled, 'security');
        Setting::setValue('security.hsts_enabled', $this->hsts_enabled, 'security');
        Setting::setValue('security.admin_2fa_required', $this->admin_2fa_required, 'security');
        Setting::setValue('security.turnstile_enabled', $this->turnstile_enabled, 'security');
        SecuritySettings::flush();

        Auditor::log('security.settings_updated', null, null, [
            'csp' => $this->csp_enabled,
            'hsts' => $this->hsts_enabled,
            'admin_2fa_required' => $this->admin_2fa_required,
            'turnstile_enabled' => $this->turnstile_enabled,
        ]);

        $this->siteSaved = 'Site protection saved — it applies immediately.';
        $this->dispatch('nx-toast', type: 'success', message: 'Site protection saved.');
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
        $this->showingSetup = true;
        $this->saved = null;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => 'required|string']);

        try {
            $confirm(Auth::user(), $this->code);
        } catch (ValidationException $e) {
            $this->addError('code', 'That code is invalid or has expired — try the current one.');

            return;
        }

        $this->showingSetup = false;
        $this->code = '';
        $this->saved = 'Two-factor authentication is on. Your admin account is now protected.';
        Auditor::log('admin.2fa_confirmed');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());
        $this->saved = 'New recovery codes generated — save them somewhere safe.';
    }

    /**
     * Only a super_admin may switch 2FA back off (self-protection against an
     * admin locking the org out of the enforcement).
     */
    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $disable(Auth::user());
        $this->showingSetup = false;
        $this->saved = 'Two-factor authentication disabled.';
        Auditor::log('admin.2fa_disabled');
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        $enabled = ! is_null($user->two_factor_secret);
        $confirmed = $enabled && ! is_null($user->two_factor_confirmed_at);

        $qr = $secret = null;
        $recoveryCodes = [];
        if ($enabled) {
            $qr = $user->twoFactorQrCodeSvg();
            $secret = decrypt($user->two_factor_secret);
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
        }

        return view('livewire.admin.security', [
            'enabled' => $enabled,
            'confirmed' => $confirmed,
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => $recoveryCodes,
            'canDisable' => $user->hasRole('super_admin'),
            'canManageSite' => $user->hasRole('super_admin'),
            'turnstileConfigured' => \App\Support\Turnstile::configured(),
            'currentCountry' => \App\Support\AdminAccess::currentCountry(request()),
            'currentIp' => request()->ip(),
        ]);
    }
}
