<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SupportSettings;
use App\Services\Support\NaaraCareAgent;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Support agent (Module 24). Configure the NaaraCare agent's human name,
 * persona/tone, and extra platform knowledge it should ground answers on.
 * super_admin & admin. The Anthropic key itself lives on the API-keys page.
 */
#[Layout('components.layouts.admin')]
class SupportAgent extends Component
{
    use WithFileUploads;

    public string $agent_name = '';

    public string $persona = '';

    public string $knowledge = '';

    /** Autopilot: let the AI resolve safe tickets itself (owner request). */
    public bool $autopilot_enabled = true;

    /** Max goodwill (USD) the AI may grant per ticket. 0 = off (it escalates). */
    public $goodwill_cap_usd = 0;

    /** Assistant avatar (shared by the helper launcher + dashboard greeting). */
    public $avatar = null;

    public string $avatar_url = '';

    /** Dashboard greeting: show it, and inline vs. dismissable popup. */
    public bool $greeting_enabled = true;

    public string $greeting_mode = 'inline';

    public ?string $saved = null;

    public function mount(): void
    {
        $this->agent_name = SupportSettings::name();
        $this->persona = SupportSettings::persona();
        $this->knowledge = SupportSettings::knowledge();
        $this->autopilot_enabled = \App\Support\SupportAutopilot::enabled();
        $this->goodwill_cap_usd = \App\Support\SupportAutopilot::goodwillCapUsd();
        $this->avatar_url = SupportSettings::avatar();
        $this->greeting_enabled = SupportSettings::greetingEnabled();
        $this->greeting_mode = SupportSettings::greetingMode();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'agent_name' => 'required|string|max:40',
            'persona' => 'required|string|max:2000',
            'knowledge' => 'nullable|string|max:20000',
            // Goodwill is deliberately capped low — it is the AI's only money lever.
            'goodwill_cap_usd' => 'required|numeric|min:0|max:20',
            // Avatar: PNG or WebP, small (it renders at ~24–40px).
            'avatar' => 'nullable|mimes:png,webp|max:512',
            'greeting_mode' => 'required|in:'.implode(',', SupportSettings::GREETING_MODES),
        ], [
            'avatar.mimes' => 'The avatar must be a PNG or WebP.',
            'avatar.max' => 'Keep the avatar under 512 KB.',
        ]);

        Setting::setValue('support.agent_name', trim($this->agent_name), 'support');
        Setting::setValue('support.persona', trim($this->persona), 'support');
        Setting::setValue('support.knowledge', trim($this->knowledge), 'support');
        Setting::setValue('support.autopilot.enabled', $this->autopilot_enabled, 'support');
        Setting::setValue('support.autopilot.goodwill_cap_usd', round((float) $this->goodwill_cap_usd, 2), 'support');
        if ($this->avatar) {
            $this->avatar_url = MediaStorage::storePublic($this->avatar, 'support');
            Setting::setValue('support.avatar', $this->avatar_url, 'support');
            $this->avatar = null;
        }
        Setting::setValue('support.greeting_enabled', $this->greeting_enabled, 'support');
        Setting::setValue('support.greeting_mode', $this->greeting_mode, 'support');
        SupportSettings::flush();
        \App\Support\SupportAutopilot::flush();

        Auditor::log('support.agent_updated', null, null, [
            'autopilot_enabled' => $this->autopilot_enabled,
            'goodwill_cap_usd' => round((float) $this->goodwill_cap_usd, 2),
        ]);
        $this->saved = 'Support agent updated.';
        $this->dispatch('nx-toast', type: 'success', message: 'Support agent updated.');
    }

    /** Remove the custom avatar — the helper + greeting fall back to a glyph. */
    public function removeAvatar(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        Setting::where('key', 'support.avatar')->get()->each->delete();
        SupportSettings::flush();
        $this->avatar_url = '';
        $this->saved = 'Avatar removed.';
    }

    public function render()
    {
        return view('livewire.admin.support-agent', [
            'configured' => app(NaaraCareAgent::class)->available(),
        ]);
    }
}
