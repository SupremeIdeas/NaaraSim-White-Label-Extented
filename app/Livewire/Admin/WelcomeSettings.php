<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\WelcomeSettings as WelcomeConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Welcome animation (first-login Aurora entrance). Toggle it on/off and
 * tune the copy, timings and blob colours — no code. Live-preview opens the real
 * /welcome screen in admin preview mode. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class WelcomeSettings extends Component
{
    public array $form = [];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->form = WelcomeConfig::all();
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $data = $this->validate([
            'form.enabled' => 'boolean',
            'form.style' => 'required|in:'.implode(',', WelcomeConfig::STYLES),
            'form.logo_reveal_speed' => 'required|integer|min:150|max:5000',
            'form.tagline_reveal_delay' => 'required|integer|min:0|max:8000',
            'form.animation_total_duration' => 'required|integer|min:1200|max:10000',
            'form.welcome_text' => 'required|string|max:60',
            'form.tagline_text' => 'required|string|max:120',
            'form.aurora_speed' => 'required|integer|min:3|max:30',
            'form.brand_color_1' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'form.brand_color_2' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ])['form'];

        WelcomeConfig::save($data);
        Auditor::log('welcome.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Welcome animation saved.');
    }

    public function resetDefaults(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        WelcomeConfig::reset();
        $this->form = WelcomeConfig::all();
        Auditor::log('welcome.settings_reset');
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to defaults.');
    }

    public function render()
    {
        return view('livewire.admin.welcome-settings');
    }
}
