<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\SplashSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Settings → Appearance → Splash Screen (blueprint Section 24.2). Edits
 * the splash.* settings; saving busts the splash cache so the change reflects
 * immediately with no redeploy. Logos are Wasabi/CDN URLs (light + dark).
 */
#[Layout('components.layouts.admin')]
class Splash extends Component
{
    use WithFileUploads;

    /** Temporary upload holders — one per logo. */
    public $product_logo_light_file;

    public $product_logo_dark_file;

    public $brand_logo_light_file;

    public $brand_logo_dark_file;

    public ?string $uploadError = null;

    #[Validate('boolean')]
    public bool $enabled = false;

    #[Validate('string|max:60')]
    public string $product_name = '';

    #[Validate('string|max:60')]
    public string $brand_tagline = '';

    #[Validate('integer|min:0|max:4000')]
    public int $duration_ms = 1400;

    #[Validate('boolean')]
    public bool $show_once_per_session = true;

    #[Validate('nullable|url')]
    public string $product_logo_light = '';

    #[Validate('nullable|url')]
    public string $product_logo_dark = '';

    #[Validate('nullable|url')]
    public string $brand_logo_light = '';

    #[Validate('nullable|url')]
    public string $brand_logo_dark = '';

    public ?string $saved = null;

    /**
     * Re-authorize on every request. save() writes splash.* settings and the
     * file-upload hook stores media to public storage — both run on the shared
     * `/livewire/update` endpoint where the route's `role:` middleware is not
     * re-applied, so the admin gate is enforced here on load AND every update.
     */
    public function booted(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $s = SplashSettings::current();
        $this->enabled = $s['enabled'];
        $this->product_name = $s['product_name'];
        $this->brand_tagline = $s['brand_tagline'];
        $this->duration_ms = $s['duration_ms'];
        $this->show_once_per_session = $s['show_once_per_session'];
        $this->product_logo_light = $s['product_logo_light'];
        $this->product_logo_dark = $s['product_logo_dark'];
        $this->brand_logo_light = $s['brand_logo_light'];
        $this->brand_logo_dark = $s['brand_logo_dark'];
    }

    /**
     * When a logo file is chosen, store it (Wasabi if configured, else the
     * server's public disk) and fill the matching URL field.
     */
    public function updated(string $name, $value): void
    {
        if (! str_ends_with($name, '_file') || ! $value) {
            return;
        }

        $this->uploadError = null;
        try {
            $this->validateOnly($name, [$name => MediaStorage::uploadRules()]);
            $url = MediaStorage::storePublic($value, 'splash');
            $this->{substr($name, 0, -5)} = $url; // e.g. product_logo_light
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->uploadError = 'Upload must be PNG, JPEG, WebP, GIF or SVG, up to '
                .round(MediaStorage::MAX_RASTER_KB / 1024).' MB.';
        } catch (\Throwable $e) {
            $this->uploadError = 'Could not save the upload. Please try again.';
        }

        $this->{$name} = null; // clear the temp file
    }

    public function save(): void
    {
        $this->validate();

        foreach ([
            'splash.enabled' => $this->enabled,
            'splash.product_name' => $this->product_name,
            'splash.brand_tagline' => $this->brand_tagline,
            'splash.duration_ms' => $this->duration_ms,
            'splash.show_once_per_session' => $this->show_once_per_session,
            'splash.product_logo_light' => $this->product_logo_light,
            'splash.product_logo_dark' => $this->product_logo_dark,
            'splash.brand_logo_light' => $this->brand_logo_light,
            'splash.brand_logo_dark' => $this->brand_logo_dark,
        ] as $key => $value) {
            Setting::setValue($key, $value, 'appearance');
        }

        // Setting::saved busts the splash cache; belt-and-suspenders here too.
        SplashSettings::flush();
        Auditor::log('splash.updated', null, null, ['enabled' => $this->enabled]);

        $this->saved = 'Splash screen saved — it updates immediately, no redeploy.';
    }

    public function render()
    {
        return view('livewire.admin.splash');
    }
}
