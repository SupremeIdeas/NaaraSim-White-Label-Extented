<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\BentoIcons as BentoIconsSupport;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Bento icons (owner request). Change the 3D illustrated icon and its
 * opacity for every product card — the homepage showcase cards and the paired
 * action tiles — with no redeploy. The card keys are fixed (they map to real
 * surfaces), so this is edit-in-place. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class BentoIcons extends Component
{
    use WithFileUploads;

    /** Per-card opacity (percent), keyed by card key. */
    public array $opacity = [];

    /** Per-card size multiplier (0.5–3.0), keyed by card key. */
    public array $scale = [];

    /** New icon uploads, keyed by card key. */
    public array $images = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        foreach (BentoIconsSupport::CARDS as $key => $def) {
            $this->opacity[$key] = BentoIconsSupport::opacity($key);
            $this->scale[$key] = BentoIconsSupport::scale($key);
        }
    }

    public function save(string $key): void
    {
        abort_unless(isset(BentoIconsSupport::CARDS[$key]), 404);

        $this->validate([
            "opacity.{$key}" => 'required|integer|min:20|max:100',
            "scale.{$key}" => 'required|numeric|min:0.5|max:3',
            "images.{$key}" => 'nullable|image|mimes:webp,png,jpg,jpeg|max:800',
        ], [
            "images.{$key}.max" => 'Keep the icon under 800 KB.',
            "images.{$key}.mimes" => 'Use a WebP, PNG or JPG icon.',
        ]);

        if (! empty($this->images[$key])) {
            $url = MediaStorage::storePublic($this->images[$key], 'bento-icons');
            Setting::setValue('bento.icon.'.$key, $url, 'bento');
            $this->images[$key] = null;
        }

        Setting::setValue('bento.opacity.'.$key, max(20, min(100, (int) $this->opacity[$key])), 'bento');
        Setting::setValue('bento.scale.'.$key, round(max(0.5, min(3.0, (float) $this->scale[$key])), 2), 'bento');

        BentoIconsSupport::flush();
        Auditor::log('bento.icon_updated', null, null, ['key' => $key]);
        $this->saved = $key;
        $this->dispatch('nx-toast', type: 'success', message: 'Bento card saved — live everywhere.');
    }

    /** Revert a card's icon + opacity to the shipped defaults. */
    public function revert(string $key): void
    {
        abort_unless(isset(BentoIconsSupport::CARDS[$key]), 404);

        Setting::where('key', 'bento.icon.'.$key)->get()->each->delete();
        Setting::where('key', 'bento.opacity.'.$key)->get()->each->delete();
        Setting::where('key', 'bento.scale.'.$key)->get()->each->delete();
        BentoIconsSupport::flush();

        $this->opacity[$key] = BentoIconsSupport::opacity($key);
        $this->scale[$key] = BentoIconsSupport::scale($key);
        Auditor::log('bento.icon_reset', null, null, ['key' => $key]);
        $this->saved = $key;
        $this->dispatch('nx-toast', type: 'success', message: 'Reverted to the shipped icon.');
    }

    public function render()
    {
        return view('livewire.admin.bento-icons', [
            'cards' => BentoIconsSupport::CARDS,
        ]);
    }
}
