<?php

namespace App\Livewire\Admin;

use App\Support\ConvaiWidget;
use App\Support\SocialAuth;
use App\Support\SocialLinks;
use App\Support\Tracking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Integrations (owner request). One home to wire the growth stack:
 *   - Social footer links (shown only once saved; hidden until accounts exist).
 *   - Facebook Pixel + Google Analytics IDs (injected only when set).
 *   - A read-only status + baked setup guide for each social sign-in provider
 *     (the credentials themselves live on the API-keys page).
 */
#[Layout('components.layouts.admin')]
class Integrations extends Component
{
    /** platform => url */
    public array $social = [];

    public string $pixelId = '';

    public string $gaId = '';

    // ElevenLabs Convai voice widget (task #17).
    public bool $convaiEnabled = false;

    public string $convaiAgentId = '';

    public string $convaiPlacement = 'both';

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);

        $existing = SocialLinks::all();
        foreach (SocialLinks::PLATFORMS as $key => $_) {
            $this->social[$key] = (string) ($existing[$key] ?? '');
        }
        $this->pixelId = (string) (Tracking::pixelId() ?? '');
        $this->gaId = (string) (Tracking::gaId() ?? '');

        $convai = ConvaiWidget::current();
        $this->convaiEnabled = $convai['enabled'];
        $this->convaiAgentId = $convai['agent_id'];
        $this->convaiPlacement = $convai['placement'];
    }

    public function saveConvai(): void
    {
        $this->validate([
            'convaiAgentId' => ['nullable', 'regex:/^[A-Za-z0-9_-]{6,64}$/'],
            'convaiPlacement' => ['required', 'in:both,customer,marketing'],
        ], [
            'convaiAgentId.regex' => 'An agent id is 6–64 letters, numbers, hyphens or underscores (from elevenlabs.io → your agent → Widget).',
        ]);

        // Guard: can't switch the widget on without an agent id to render.
        if ($this->convaiEnabled && ConvaiWidget::sanitizeId($this->convaiAgentId) === '') {
            $this->addError('convaiAgentId', 'Add your Convai agent id before enabling the widget.');

            return;
        }

        ConvaiWidget::save($this->convaiEnabled, $this->convaiAgentId, $this->convaiPlacement);
        $this->saved = 'Voice widget saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Voice widget saved.');
    }

    public function saveSocial(): void
    {
        SocialLinks::save($this->social);
        $this->saved = 'Social links saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Social links saved.');
    }

    public function saveTracking(): void
    {
        $this->validate([
            'pixelId' => ['nullable', 'regex:/^\d{6,20}$/'],
            'gaId' => ['nullable', 'regex:/^G-[A-Za-z0-9]{6,20}$/'],
        ], [
            'pixelId.regex' => 'A Facebook Pixel ID is numeric (15–16 digits).',
            'gaId.regex' => 'A GA4 Measurement ID looks like G-XXXXXXXXXX.',
        ]);

        Tracking::save($this->pixelId, $this->gaId);
        $this->saved = 'Tracking saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Tracking saved.');
    }

    public function render()
    {
        // Per-provider status + baked guide, with the exact callback URL to paste.
        $providers = collect(SocialAuth::PROVIDERS)->map(fn ($meta, $key) => $meta + [
            'key' => $key,
            'enabled' => SocialAuth::enabled($key),
            'callback' => SocialAuth::redirect($key),
        ])->values()->all();

        return view('livewire.admin.integrations', [
            'platforms' => SocialLinks::PLATFORMS,
            'providers' => $providers,
            'convaiPlacements' => ConvaiWidget::PLACEMENTS,
        ]);
    }
}
