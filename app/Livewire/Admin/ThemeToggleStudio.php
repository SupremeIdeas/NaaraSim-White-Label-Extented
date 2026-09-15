<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\ThemeToggleSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Theme Toggle Studio. Pick the site-wide dark/light switch style —
 * a single global setting (unlike Preloader Studio's per-page-type map,
 * there's exactly one toggle button, so one selection covers it everywhere).
 * super_admin/admin only.
 */
#[Layout('components.layouts.admin')]
class ThemeToggleStudio extends Component
{
    public string $style;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->style = ThemeToggleSettings::current();
    }

    public function selectStyle(string $slug): void
    {
        if (isset(ThemeToggleSettings::availablePresets()[$slug])) {
            $this->style = $slug;
            $this->saved = null;
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(isset(ThemeToggleSettings::availablePresets()[$this->style]), 403);

        ThemeToggleSettings::save($this->style);

        Auditor::log('theme_toggle.style_updated', null, null, ['style' => $this->style]);
        $this->saved = $this->style;
        $this->dispatch('nx-toast', type: 'success', message: 'Theme toggle style saved — live immediately.');
    }

    public function render()
    {
        return view('livewire.admin.theme-toggle-studio', [
            'presets' => ThemeToggleSettings::availablePresets(),
        ]);
    }
}
