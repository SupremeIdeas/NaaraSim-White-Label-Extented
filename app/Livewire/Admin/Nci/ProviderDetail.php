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
