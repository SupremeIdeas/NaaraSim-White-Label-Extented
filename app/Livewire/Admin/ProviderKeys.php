<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\eSIM\CatalogueSyncService;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ProviderKeys as ProviderKeysStore;
use App\Support\ProviderStatus;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → API Keys (blueprint Sections 15 & 17.4, money-safety rule 10).
 * Super-admin only. Paste each provider/gateway/integration credential once
 * and it takes effect immediately — no .env editing, no redeploy, no coding.
 * Saved values are encrypted at rest and never echoed back to the browser:
 * the form shows a masked preview and blank inputs, so submitting an empty
 * field LEAVES the stored secret untouched. Providers flip Active as soon as
 * their required keys are present (mirrors the storefront "Coming Soon" gate).
 */
#[Layout('components.layouts.admin')]
class ProviderKeys extends Component
{
    /** field-name => new value typed by the admin (blank = leave as-is). */
    public array $inputs = [];

    /** Admin's chosen primary media store: auto|r2|wasabi|public (BUILD-11 §4). */
    public string $primaryDisk = 'auto';

    public ?string $saved = null;

    public function mount(): void
    {
        // Always start blank — we never send stored secrets to the browser.
        foreach (array_keys(ProviderKeysStore::fieldMap()) as $field) {
            $this->inputs[$field] = '';
        }
        $this->primaryDisk = MediaStorage::primaryPreference();
    }

    /**
     * Persist which object store wins for media (BUILD-11 §4). 'auto' prefers
     * R2 → Wasabi → local; an explicit choice that isn't actually configured
     * still falls through to auto inside MediaStorage, so this can never brick
     * uploads. A plain (non-secret) Setting, saved on its own control.
     */
    public function savePrimaryDisk(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        if (! in_array($this->primaryDisk, ['auto', 'r2', 'wasabi', 'public'], true)) {
            $this->primaryDisk = 'auto';
        }

        Setting::setValue('media.primary_disk', $this->primaryDisk, 'media', 'Which object store serves media (auto|r2|wasabi|public).');
        Auditor::log('media.primary_disk_updated', null, null, ['disk' => $this->primaryDisk]);

        $this->dispatch('nx-toast', type: 'success', message: 'Primary media store set to '.$this->primaryDisk.'.');
    }

    /**
     * Save only the fields the admin actually typed into. A blank field is a
     * no-op (keeps whatever is stored) rather than an erase, so the operator
     * can update one key without re-pasting the rest.
     */
    public function save(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $changed = array_filter(
            $this->inputs,
            fn ($v) => is_string($v) && trim($v) !== '',
        );

        if ($changed !== []) {
            ProviderKeysStore::save($changed);

            // Audit WHICH keys changed — never the values themselves.
            Auditor::log('providers.keys_updated', null, null, [
                'fields' => array_keys($changed),
            ]);
        }

        // Clear the inputs so secrets don't linger in the component state.
        foreach ($this->inputs as $field => $_) {
            $this->inputs[$field] = '';
        }

        $this->saved = $changed === []
            ? 'Nothing to save — enter a key to update it.'
            : 'Saved. Web requests use the new keys immediately; background workers will pick them up within a few seconds (they were signalled to restart).';
    }

    /** Force an immediate, synchronous catalogue sync for one eSIM provider. */
    public function syncNow(string $provider): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
        if (! in_array($provider, ['esimgo', 'airalo', 'quibity', 'zendit', 'oneglobal', 'montymobile', 'gigs'], true)) {
            return;
        }

        try {
            $count = app(CatalogueSyncService::class)->sync($provider);
            Auditor::log('esim.sync_now', null, null, ['provider' => $provider, 'count' => $count]);
            $this->dispatch('nx-toast', type: 'success', message: ucfirst($provider).": synced {$count} plans.");
        } catch (\Throwable $e) {
            // SyncStatus already recorded the failure; surface it to the admin.
            $this->dispatch('nx-toast', type: 'error', message: ucfirst($provider).' sync failed: '.Str::limit($e->getMessage(), 100));
        }
    }

    public function render()
    {
        // Build a display model: the schema plus each field's masked preview
        // and each provider's live Active/Coming-Soon status.
        $schema = ProviderKeysStore::schema();
        $previews = [];
        foreach (ProviderKeysStore::fieldMap() as $field => $_) {
            $previews[$field] = ProviderKeysStore::preview($field);
        }

        return view('livewire.admin.provider-keys', [
            'schema' => $schema,
            'previews' => $previews,
            'statuses' => ProviderStatus::all(),
            // What media storage actually resolves to right now, so the admin can
            // see the effect of their primary-store choice + saved R2 credentials.
            'activeMediaDisk' => MediaStorage::disk(),
            'r2Ready' => MediaStorage::r2Configured(),
            // Per-eSIM-provider last-sync outcome (esim_upgrade Part 1).
            'syncStatus' => [
                'esimgo' => SyncStatus::for('esimgo'),
                'airalo' => SyncStatus::for('airalo'),
                'quibity' => SyncStatus::for('quibity'),
                'zendit' => SyncStatus::for('zendit'),
                'oneglobal' => SyncStatus::for('oneglobal'),
                'montymobile' => SyncStatus::for('montymobile'),
                'gigs' => SyncStatus::for('gigs'),
            ],
        ]);
    }
}
