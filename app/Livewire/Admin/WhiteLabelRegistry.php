<?php

namespace App\Livewire\Admin;

use App\Exceptions\LicenseActivationException;
use App\Models\DistributedPackage;
use App\Models\Setting;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\PackagePublisher;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → White-Label Oversight (Batch 4 §2) + License Authority (Batch 6). The
 * central view of who is a registered white-label brand, what tier/status they're
 * on, and — per brand — every call their deployed copy has made to the
 * distribution API. Batch 6 adds the brand-onboarding controls: issue a license
 * (mint a key + set a tier + activate), approve/reject a self-serve registration
 * request, suspend/restore/revoke, regenerate a leaked key, and — the firewalled-
 * fork fallback — hand a token directly.
 *
 * super_admin/admin gate, like every other sensitive admin screen. A freshly
 * minted license key is shown in a one-time banner: it's the credential handed to
 * the buyer, and while it's not a bearer token, there's no reason to keep echoing
 * it after issuance. A directly-issued API token IS a bearer credential and is
 * shown once, never persisted in view state beyond the immediate reveal.
 */
#[Layout('components.layouts.admin')]
class WhiteLabelRegistry extends Component
{
    /** The brand whose API-call history is being drilled into, if any. */
    public ?int $selectedInstanceId = null;

    // Issue-license form.
    public string $newBrand = '';

    public string $newEmail = '';

    public string $newTier = WhiteLabelInstance::TIER_NORMAL;

    // One-time credential reveal (cleared on the next action / dismiss).
    public ?int $revealedForId = null;

    public ?string $revealedKey = null;

    public ?string $revealedToken = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function getApiEnabledProperty(): bool
    {
        return (bool) Setting::getValue('white_label_api.enabled', false);
    }

    public function getTierOptionsProperty(): array
    {
        return WhiteLabelInstance::TIERS;
    }

    public function toggleApi(): void
    {
        $this->guard();

        $new = ! $this->apiEnabled;
        Setting::setValue('white_label_api.enabled', $new);
        Auditor::log('white_label_api.toggled', null, null, ['enabled' => $new]);
        $this->dispatch('nx-toast', type: 'success', message: 'White-label API '.($new ? 'enabled' : 'disabled').'.');
    }

    /** Create a brand-new instance and immediately issue it a license + key. */
    public function issueNewLicense(WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $data = $this->validate([
            'newBrand' => ['required', 'string', 'max:120'],
            'newEmail' => ['required', 'email', 'max:255'],
            'newTier' => ['required', 'in:'.implode(',', WhiteLabelInstance::TIERS)],
        ]);

        $instance = $licenses->register([
            'brand_name' => $data['newBrand'],
            'contact_email' => $data['newEmail'],
        ]);
        $licenses->issueLicense($instance, $data['newTier'], Auth::id());

        $this->reset('newBrand', 'newEmail');
        $this->newTier = WhiteLabelInstance::TIER_NORMAL;
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'License issued for '.$instance->brand_name.'.');
    }

    /** Approve a pending registration request at a chosen tier (issues the key). */
    public function approveInstance(int $id, string $tier, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        $licenses->issueLicense($instance, $tier, Auth::id());
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'Approved and licensed.');
    }

    /** Generate a fresh key on an existing instance (a leaked key replacement). */
    public function regenerateKey(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        $licenses->issueLicense($instance, $instance->tier ?? WhiteLabelInstance::TIER_NORMAL, Auth::id());
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'New license key generated. The old key no longer works.');
    }

    /** Direct token issuance — the fallback for a fork that can't reach activate. */
    public function issueTokenFor(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        try {
            $token = $licenses->issueTokenDirectly($instance);
        } catch (LicenseActivationException $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Cannot issue a token: the instance needs a live license and active status.');

            return;
        }

        $this->revealCredential($instance->id, token: $token);
        $this->dispatch('nx-toast', type: 'success', message: 'API token issued — copy it now, it will not be shown again.');
    }

    public function rejectInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->reject($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'Registration rejected.');
        }
    }

    public function suspendInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->suspend($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'Instance suspended — its token has been revoked.');
        }
    }

    public function restoreInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->restore($instance, Auth::id());
            $this->dispatch('nx-toast', type: 'success', message: 'Instance restored. It must re-activate with its key to get a new token.');
        }
    }

    public function revokeLicense(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->revokeLicense($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'License permanently revoked — the key can never be used again.');
        }
    }

    /**
     * Raise/lower an instance's feature-entitlement level (Batch 8) — the
     * operator's "this Normal fork has paid up" action (basic → standard), or
     * any admin-chosen level. Feature-lock change only; never touches the token,
     * key, or package tier.
     */
    public function setLevel(int $id, string $level, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->setEntitlementLevel($instance, $level);
            $this->dispatch('nx-toast', type: 'success', message: 'Feature level set to '.$instance->fresh()->entitlement_level.'. The fork unlocks on its next check-in.');
        }
    }

    public function getFeatureCatalogProperty(): array
    {
        return \App\Support\FeatureLocks::catalog();
    }

    public function getFeatureLevelsProperty(): array
    {
        return WhiteLabelInstance::LEVELS;
    }

    public function getFeatureLocksProperty(): array
    {
        return \App\Support\FeatureLocks::all();
    }

    /** Toggle whether one feature is locked at one level (admin config). */
    public function toggleFeatureLock(string $level, string $key): void
    {
        $this->guard();

        if (! in_array($level, WhiteLabelInstance::LEVELS, true) || ! array_key_exists($key, \App\Support\FeatureLocks::catalog())) {
            return;
        }

        $current = \App\Support\FeatureLocks::all()[$level] ?? [];
        $next = in_array($key, $current, true)
            ? array_values(array_diff($current, [$key]))
            : array_values(array_merge($current, [$key]));

        \App\Support\FeatureLocks::saveLevel($level, $next);
        Auditor::log('white_label.feature_lock_toggled', null, null, ['level' => $level, 'key' => $key]);
        $this->dispatch('nx-toast', type: 'success', message: 'Feature locks updated for '.$level.'.');
    }

    private function revealCredential(int $id, ?string $key = null, ?string $token = null): void
    {
        $this->revealedForId = $id;
        $this->revealedKey = $key;
        $this->revealedToken = $token;
    }

    public function clearReveal(): void
    {
        $this->reset('revealedForId', 'revealedKey', 'revealedToken');
    }

    public function selectInstance(int $id): void
    {
        $this->selectedInstanceId = $this->selectedInstanceId === $id ? null : $id;
    }

    public function togglePublish(int $packageId, PackagePublisher $publisher): void
    {
        $this->guard();

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        // Defense in depth: the Blade view never renders a clickable toggle
        // for a master-only package (a locked badge shows instead) — this
        // catch is only for a direct wire:click call that bypasses the UI.
        // setPublished() is the real, server-side gate; this just turns its
        // loud rejection into a clean toast instead of a Livewire error.
        try {
            $publisher->setPublished($package, ! $package->is_published, Auth::id());
        } catch (\RuntimeException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Package '.($package->fresh()->is_published ? 'published' : 'unpublished').'.');
    }

    public function withdrawPackage(int $packageId, PackagePublisher $publisher): void
    {
        $this->guard();

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        $publisher->withdraw($package, Auth::id());
        $this->dispatch('nx-toast', type: 'success', message: 'Package withdrawn from distribution.');
    }

    public function getInstancesProperty()
    {
        return WhiteLabelInstance::query()->latest()->get();
    }

    public function getPackagesProperty()
    {
        return DistributedPackage::query()->latest()->get();
    }

    public function getSelectedLogsProperty()
    {
        if ($this->selectedInstanceId === null) {
            return collect();
        }

        return WhiteLabelApiLog::query()
            ->where('white_label_instance_id', $this->selectedInstanceId)
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.white-label-registry');
    }
}
