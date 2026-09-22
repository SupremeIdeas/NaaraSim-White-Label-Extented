<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\BannerPlacements as BannerPlacementsSupport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Frontend-UX-fix blueprint Phase G — admin control for the "More from
 * Naara" banner carousel's placement system (App\Support\BannerPlacements).
 * Per placement: on/off + display style. Re-authorized every request, same
 * gate as every other content-editing admin screen in this codebase.
 */
#[Layout('components.layouts.admin')]
class BannerPlacements extends Component
{
    /** Per-placement editable fields, keyed by placement key. */
    public array $form = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        foreach (array_keys(BannerPlacementsSupport::PLACEMENTS) as $key) {
            $this->form[$key] = BannerPlacementsSupport::config($key);
        }
    }

    public function toggle(string $placement): void
    {
        abort_unless(isset($this->form[$placement]), 404);

        $this->form[$placement]['enabled'] = ! ($this->form[$placement]['enabled'] ?? false);
        $this->save($placement);
    }

    public function save(string $placement): void
    {
        abort_unless(isset($this->form[$placement]), 404);

        $data = $this->validate([
            "form.{$placement}.enabled" => 'required|boolean',
            "form.{$placement}.display_style" => 'required|in:'.implode(',', array_keys(BannerPlacementsSupport::DISPLAY_STYLES)),
        ])['form'][$placement];

        BannerPlacementsSupport::save($placement, (bool) $data['enabled'], $data['display_style']);

        Auditor::log('banners.placement_updated', payload: ['placement' => $placement] + $data);
        $this->saved = $placement;
        $this->dispatch('nx-toast', type: 'success', message: 'Banner placement saved.');
    }

    public function render()
    {
        return view('livewire.admin.banner-placements', [
            'placements' => BannerPlacementsSupport::PLACEMENTS,
            'displayStyles' => BannerPlacementsSupport::DISPLAY_STYLES,
        ]);
    }
}
