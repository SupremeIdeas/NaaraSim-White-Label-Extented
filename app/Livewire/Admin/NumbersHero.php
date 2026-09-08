<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\NumbersHeroContent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Numbers hero (Numbers V6 §0). Manage the Numbers landing hero: title,
 * description, and up to four ordered images for the interchanging-reveal hero.
 * Upload replaces a slot; clearing all falls back to the shipped seed images.
 * Mirrors the eSIM hero admin. Re-authorized on every request via booted().
 */
#[Layout('components.layouts.admin')]
class NumbersHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    /** @var list<string> current image URLs (0–4). */
    public array $images = [];

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
        $c = NumbersHeroContent::current();
        $this->title = $c['title'];
        $this->description = $c['description'];
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
            'slot0' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot1' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot2' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'slot3' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ]);

        $images = $this->images;
        foreach (['slot0', 'slot1', 'slot2', 'slot3'] as $slot) {
            if ($this->{$slot}) {
                $images[] = MediaStorage::storePublic($this->{$slot}, 'numbers-hero');
                $this->{$slot} = null;
            }
        }
        $images = array_slice(array_values(array_unique($images)), 0, NumbersHeroContent::MAX_IMAGES);

        Setting::setValue(NumbersHeroContent::TITLE_KEY, trim($this->title), 'numbers');
        Setting::setValue(NumbersHeroContent::DESC_KEY, trim($this->description), 'numbers');
        Setting::setValue(NumbersHeroContent::IMAGES_KEY, $images, 'numbers');
        NumbersHeroContent::flush();

        $this->images = $images;
        Auditor::log('numbers.hero_updated', null, null, ['count' => count($images)]);
        $this->saved = 'Numbers hero saved.';
    }

    public function render()
    {
        return view('livewire.admin.numbers-hero', ['maxImages' => NumbersHeroContent::MAX_IMAGES]);
    }
}
