<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\GiftHeroBackground;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Naara Gift hero (owner request). Manages the SAME visual hero system
 * as the customer dashboard home (title/description/light+dark image/on-off/
 * size), under its own `giftcard.hero.*` setting namespace so it can be
 * re-themed independently of the dashboard hero. Mirrors Admin\Branding's
 * "Dashboard home hero" section field-for-field.
 */
#[Layout('components.layouts.admin')]
class GiftHero extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $title_size = GiftHeroBackground::DEFAULT_TITLE_SIZE;

    public string $description = '';

    public bool $enabled = true;

    public $hero_light = null;

    public $hero_dark = null;

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $c = GiftHeroBackground::current();
        $this->title = $c['title'];
        $this->title_size = $c['title_size'];
        $this->description = $c['description'];
        $this->enabled = $c['enabled'];
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'nullable|string|max:40',
            'title_size' => 'required|in:'.implode(',', array_keys(GiftHeroBackground::TITLE_SIZES)),
            'description' => 'nullable|string|max:120',
            'hero_light' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_dark' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ], [
            'title.max' => 'Keep the hero title short (40 characters) so it still fits the layout.',
            'description.max' => 'Keep the description to one short line (120 characters).',
            'hero_light.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dark.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_light.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_dark.max' => 'Keep the hero image under 600 KB for fast loading.',
        ]);

        Setting::setValue(GiftHeroBackground::TITLE_KEY, trim($this->title), 'giftcards');
        Setting::setValue(GiftHeroBackground::TITLE_SIZE_KEY, $this->title_size, 'giftcards');
        Setting::setValue(GiftHeroBackground::DESC_KEY, trim($this->description), 'giftcards');
        Setting::setValue(GiftHeroBackground::ENABLED_KEY, $this->enabled, 'giftcards');

        foreach (['hero_light' => GiftHeroBackground::LIGHT_KEY, 'hero_dark' => GiftHeroBackground::DARK_KEY] as $field => $key) {
            if ($this->{$field}) {
                Setting::setValue($key, MediaStorage::storePublic($this->{$field}, 'giftcard-hero'), 'giftcards');
                $this->{$field} = null;
            }
        }

        GiftHeroBackground::flush();
        $this->title = GiftHeroBackground::title();
        $this->description = GiftHeroBackground::description();
        Auditor::log('giftcard.hero_updated');
        $this->saved = 'Naara Gift hero saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Gift hero saved — live now.');
    }

    /** Remove the hero images — the gift page falls back to text-only. */
    public function removeImages(): void
    {
        foreach ([GiftHeroBackground::LIGHT_KEY, GiftHeroBackground::DARK_KEY] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        GiftHeroBackground::flush();
        Auditor::log('giftcard.hero_images_removed');
        $this->saved = 'Hero images removed — the gift page uses text only.';
        $this->dispatch('nx-toast', type: 'success', message: 'Hero images removed.');
    }

    public function render()
    {
        return view('livewire.admin.gift-hero', [
            'currentLight' => GiftHeroBackground::light(),
            'currentDark' => GiftHeroBackground::dark(),
            'isSet' => GiftHeroBackground::isSet(),
        ]);
    }
}
