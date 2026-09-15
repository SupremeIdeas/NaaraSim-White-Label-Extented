<?php

namespace App\Livewire\Admin\Nci;

use App\Models\ProviderOutcome;
use App\Models\ProviderRegistry;
use App\Services\Routing\CircuitBreaker;
use App\Support\OperationsCenter;
use App\Support\ProviderKeys;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NAARA-BUILD-17 §1.2 — Provider Detail. Real name + logo up top with the
 * primary "Open Provider Dashboard" action (the most-used action for an admin
 * who just onboarded a provider), then tabs: Overview (live-truth + a
 * password-gated credential reveal, reusing the existing sensitive-action
 * pattern), Circuit & Outcomes (state + recent outcomes + manual open/reset that
 * call CircuitBreaker's own methods), NCI Intelligence (read-only), Config
 * (links to the existing per-provider settings — never duplicated here).
 */
#[Layout('components.layouts.admin')]
class ProviderDetail extends Component
{
    public string $provider;

    public string $tab = 'overview';

    /** Password re-entry to reveal secrets (same rule as Account email/password). */
    public string $revealPassword = '';

    public bool $revealed = false;

    /** Optional note captured in the audit log when pausing — never stored on
     *  the registry row itself, keeping the schema minimal. */
    public string $pauseReason = '';

    public function mount(string $provider): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.view'), 403);
        abort_unless(ProviderRegistry::where('provider_key', $provider)->exists(), 404);
        $this->provider = $provider;
    }

    /** Reveal the real credential values — requires the admin's current password. */
    public function reveal(): void
    {
        $this->validate(
            ['revealPassword' => ['required', 'current_password:web']],
            ['revealPassword.current_password' => 'That password is incorrect.'],
        );
        $this->revealed = true;
        $this->reset('revealPassword');
    }

    public function hideSecrets(): void
    {
        $this->revealed = false;
    }

    /**
     * Owner request (2026-09-15) — a durable way to take a provider fully out
     * of service without editing .env or the settings row by hand: erase
     * every stored credential field for this provider (same "blank = delete"
     * semantics ProviderKeys::save() already has, just no longer filtered
     * out). ProviderStatus::isActive() then reads false and every existing
     * "Coming Soon" gate (ProviderModels::status(), storefront CTAs, the
     * health probe) reacts on its own — nothing else needs to change.
     */
    public function clearKeys(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);

        $fields = [];
        $configPaths = [];
        foreach (ProviderKeys::schema() as $group) {
            foreach ($group['fields'] ?? [] as $field => $meta) {
                if (str_starts_with($field, $this->provider.'_')) {
                    $fields[$field] = '';
                    // ProviderKeys::save() only STOPS overlaying the admin value —
                    // it never resets config() itself (by design, so .env keeps
                    // filling an untouched field). Re-derive each cleared path
                    // from its raw env var right now, the same value a fresh
                    // request's boot would compute, so this takes effect THIS
                    // request too, not just the next one.
                    $configPaths[$meta['config']] = $meta['env'];
                }
            }
        }
        if ($fields === []) {
            return;
        }

        ProviderKeys::save($fields);
        foreach ($configPaths as $configPath => $envVar) {
            config([$configPath => env($envVar)]);
        }

        \App\Support\Auditor::log('providers.key_cleared', 'ProviderRegistry', null, [
            'provider' => $this->provider,
            'fields' => array_keys($fields),
        ]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($this->provider).'\'s API keys cleared — it reverts to Coming Soon.');
    }

    private function assertOverride(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.override'), 403);
    }

    /** Manual circuit control — calls CircuitBreaker's own transition methods (§0). */
    public function openCircuit(CircuitBreaker $breaker): void
    {
        $this->assertOverride();
        $breaker->forceOpen($this->provider);
        \App\Support\Auditor::log('nci.circuit_forced', 'ProviderRegistry', null, ['provider' => $this->provider, 'to' => 'open']);
        $this->dispatch('nx-toast', type: 'success', message: 'Circuit opened for '.$this->provider.'.');
    }

    public function closeCircuit(CircuitBreaker $breaker): void
    {
        $this->assertOverride();
        $breaker->forceClose($this->provider);
        \App\Support\Auditor::log('nci.circuit_forced', 'ProviderRegistry', null, ['provider' => $this->provider, 'to' => 'closed']);
        $this->dispatch('nx-toast', type: 'success', message: 'Circuit reset to closed for '.$this->provider.'.');
    }

    /**
     * Owner request (2026-09-15) — a durable "sleep" for this provider: routing
     * (CircuitBreaker::allows(), the one real gate every router already calls)
     * refuses it outright, health checks stop probing/alerting on it, and —
     * unlike an open circuit — it never self-heals on a cooldown timer. Only
     * resume() lifts it.
     */
    public function pause(): void
    {
        $this->assertOverride();

        $row = ProviderRegistry::where('provider_key', $this->provider)->firstOrFail();
        $row->forceFill(['paused_at' => now()])->save();
        ProviderRegistry::flushSnapshot();

        \App\Support\Auditor::log('nci.provider_paused', 'ProviderRegistry', $row->id, [
            'provider' => $this->provider,
            'reason' => trim($this->pauseReason) ?: null,
        ]);
        $this->reset('pauseReason');
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($this->provider).' paused — excluded from routing until resumed.');
    }

    public function resume(): void
    {
        $this->assertOverride();

        $row = ProviderRegistry::where('provider_key', $this->provider)->firstOrFail();
        $row->forceFill(['paused_at' => null])->save();
        ProviderRegistry::flushSnapshot();

        \App\Support\Auditor::log('nci.provider_resumed', 'ProviderRegistry', $row->id, ['provider' => $this->provider]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($this->provider).' resumed — back in rotation.');
    }

    /** The provider's credential fields (masked preview, raw only when revealed). */
    private function credentials(): array
    {
        $saved = $this->revealed ? ProviderKeys::saved() : [];
        $fields = [];
        foreach (ProviderKeys::schema() as $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                if (! str_starts_with($key, $this->provider.'_')) {
                    continue;
                }
                $fields[] = [
                    'key' => $key,
                    'label' => $field['label'],
                    'secret' => (bool) ($field['secret'] ?? false),
                    'has' => ProviderKeys::hasValue($key),
                    'preview' => ProviderKeys::preview($key),
                    'raw' => $this->revealed ? ($saved[$key] ?? null) : null,
                ];
            }
        }

        return $fields;
    }

    public function render()
    {
        $row = ProviderRegistry::where('provider_key', $this->provider)->firstOrFail();

        return view('livewire.admin.nci.provider-detail', [
            'row' => $row,
            'families' => array_map(fn ($f) => OperationsCenter::familyName($f), $row->product_families ?? []),
            'tierLabel' => OperationsCenter::tierLabel($row->onboarding_tier),
            'credentials' => $this->credentials(),
            'outcomes' => ProviderOutcome::where('provider_key', $this->provider)->latest('occurred_at')->limit(20)->get(),
            'canOverride' => Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.override'),
        ]);
    }
}
