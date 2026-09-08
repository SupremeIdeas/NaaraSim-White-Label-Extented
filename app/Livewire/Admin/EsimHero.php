<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\EsimHeroContent;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → eSIM hero (esim_upgrade Part 2). Manage the storefront hero: title,
 * description, and up to four ordered images for the interchanging-reveal hero.
 * Upload replaces a slot; clearing all falls back to the shipped seed images.
 * Generic upload/reorder so images can be swapped later without a deploy.
 *
 * Re-authorized on every request via booted().
 */
#[Layout('components.layouts.admin')]
class EsimHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    // The catalogue section heading + subheading below the hero (admin-editable).
    public string $section_title = '';

    public string $section_subtitle = '';

    /** @var list<string> current image URLs (0–4). */
    public array $images = [];

    /** New uploads keyed by slot index. */
    public $slot0 = null;

    public $slot1 = null;

    public $slot2 = null;

    public $slot3 = null;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $c = EsimHeroContent::current();
        $this->title = $c['title'];
        $this->description = $c['description'];
        $this->section_title = $c['section_title'];
        $this->section_subtitle = $c['section_subtitle'];
        $this->images = $c['images'];
    }

    public function removeImage(int $i): void
    {
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:250',
            'section_title' => 'required|string|max:60',
            'section_subtitle' => 'required|string|max:160',
            'slot0' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot1' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot2' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot3' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ]);

        // Fold any new uploads into the ordered image list.
        $images = $this->images;
        foreach (['slot0', 'slot1', 'slot2', 'slot3'] as $slot) {
            if ($this->{$slot}) {
                $images[] = MediaStorage::storePublic($this->{$slot}, 'esim-hero');
                $this->{$slot} = null;
            }
        }
        $images = array_slice(array_values(array_unique($images)), 0, EsimHeroContent::MAX_IMAGES);

        Setting::setValue(EsimHeroContent::TITLE_KEY, trim($this->title), 'esim');
        Setting::setValue(EsimHeroContent::DESC_KEY, trim($this->description), 'esim');
        Setting::setValue(EsimHeroContent::SECTION_TITLE_KEY, trim($this->section_title), 'esim');
        Setting::setValue(EsimHeroContent::SECTION_SUBTITLE_KEY, trim($this->section_subtitle), 'esim');
        Setting::setValue(EsimHeroContent::IMAGES_KEY, $images, 'esim');
        EsimHeroContent::flush();

        $this->images = $images;
        Auditor::log('esim.hero_updated', null, null, ['count' => count($images)]);
        $this->saved = 'eSIM hero saved.';
    }

    public function render()
    {
        return view('livewire.admin.esim-hero', ['maxImages' => EsimHeroContent::MAX_IMAGES]);
    }
}
