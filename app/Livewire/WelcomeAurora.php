<?php

namespace App\Livewire;

use App\Support\WelcomeSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Aurora Welcome Animation (first-login premium entrance). Shown once, right
 * after signup — a fullscreen aurora + logo-zoom + tagline sequence that then
 * hands off to the dashboard. It only ever runs when `just_registered` is set
 * (consumed by RegisterResponse) or an admin explicitly previews it, so it can
 * never nag a returning user. 100% CSS + Alpine — no external JS.
 */
#[Layout('components.layouts.app')]
class WelcomeAurora extends Component
{
    /** Admin-only live preview switch (?preview=1 from the settings page). */
    #[Url]
    public bool $preview = false;

    public bool $showWelcome = false;

    public array $settings = [];

    public function mount()
    {
        $this->settings = WelcomeSettings::all();

        $canPreview = $this->preview && Auth::user()?->hasAnyRole(['super_admin', 'admin']);
        $this->showWelcome = $canPreview || (bool) session()->pull('just_registered', false);

        // Not a genuine first-login (or the animation is disabled) → straight to
        // the dashboard, no flash of the overlay.
        if (! $this->showWelcome || ! ($this->settings['enabled'] ?? true)) {
            return $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function redirectToDashboard()
    {
        return $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.welcome-aurora');
    }
}
