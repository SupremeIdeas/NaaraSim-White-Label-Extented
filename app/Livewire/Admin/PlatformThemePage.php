<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\GlassmorphismSettings;
use App\Support\MediaStorage;
use App\Support\PlatformTheme;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Dashboard theme (Platform Theme). Pick the dashboard-wide background
 * mode and tune the brand-gradient blooms with a live preview. The gradient
 * tuning is edited entirely client-side (Alpine) against a faithful preview
 * built from the SAME --dbg-* formula the real background uses, then committed
 * here in one save() — so the preview is instant with no round-trip. Wallpaper
 * uploads (image mode) go through MediaStorage, which already stores locally
 * and switches to Wasabi automatically once live keys exist.
 */
#[Layout('components.layouts.admin')]
class PlatformThemePage extends Component
{
    use WithFileUploads;

    /** The whole config, seeded into Alpine for the live editor. */
    public array $config = [];

    /** Wallpaper uploads (image mode) — light + dark, WebP, kept small. */
    public $image_light = null;

    public $image_dark = null;

    public ?string $saved = null;

    /** Card glassmorphism dial (owner request) — independent of the wallpaper
     *  config above; a plain server round-trip, no Alpine live-preview needed
     *  for two sliders. */
    public int $glass_opacity = GlassmorphismSettings::DEFAULT_OPACITY;

    public int $glass_blur = GlassmorphismSettings::DEFAULT_BLUR;

    /** Curated static/animated presets (a LIGHT tuning; dark is auto-derived). */
    public const PRESETS = [
        'signature' => ['label' => 'Signature', 'up_intensity' => 1.0, 'lo_intensity' => 1.0, 'up_feather' => 82, 'lo_feather' => 88, 'up_size' => 65, 'lo_size' => 70, 'extra_color' => 'primary', 'extra_alpha' => 0.0],
        'soft' => ['label' => 'Soft', 'up_intensity' => 0.7, 'lo_intensity' => 0.65, 'up_feather' => 90, 'lo_feather' => 90, 'up_size' => 72, 'lo_size' => 76, 'extra_color' => 'primary', 'extra_alpha' => 0.0],
        'vivid' => ['label' => 'Vivid', 'up_intensity' => 1.3, 'lo_intensity' => 1.15, 'up_feather' => 76, 'lo_feather' => 80, 'up_size' => 60, 'lo_size' => 64, 'extra_color' => 'accent', 'extra_alpha' => 0.08],
        'expansive' => ['label' => 'Expansive', 'up_intensity' => 1.0, 'lo_intensity' => 0.9, 'up_feather' => 88, 'lo_feather' => 90, 'up_size' => 85, 'lo_size' => 88, 'extra_color' => 'primary', 'extra_alpha' => 0.0],
    ];

    public function mount(): void
    {
        $c = PlatformTheme::current();
        $this->config = [
            'mode' => $c['mode'],
            'light' => $c['light'],
            'dark' => $c['dark'],
            'image_light' => $c['image_light'],
            'image_dark' => $c['image_dark'],
            // When dark equals the derived dark of light, keep them linked.
            'customize_dark' => $c['dark'] !== PlatformTheme::deriveDark($c['light']),
        ];

        $glass = GlassmorphismSettings::current();
        $this->glass_opacity = $glass['opacity'];
        $this->glass_blur = $glass['blur'];
    }

    /** Save the card glassmorphism dial — independent of the wallpaper save()
     *  above so tuning one never touches the other. */
    public function saveGlass(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'glass_opacity' => 'required|integer|min:'.GlassmorphismSettings::MIN_OPACITY.'|max:'.GlassmorphismSettings::MAX_OPACITY,
            'glass_blur' => 'required|integer|min:'.GlassmorphismSettings::MIN_BLUR.'|max:'.GlassmorphismSettings::MAX_BLUR,
        ]);

        GlassmorphismSettings::save($this->glass_opacity, $this->glass_blur);
        Auditor::log('platform.glass_updated', payload: ['opacity' => $this->glass_opacity, 'blur' => $this->glass_blur]);
        $this->dispatch('nx-toast', type: 'success', message: 'Card glassmorphism saved — live across the app.');
    }

    /** Snap the glass dial back to the shipped default. */
    public function resetGlass(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->glass_opacity = GlassmorphismSettings::DEFAULT_OPACITY;
        $this->glass_blur = GlassmorphismSettings::DEFAULT_BLUR;
        GlassmorphismSettings::save($this->glass_opacity, $this->glass_blur);
        $this->dispatch('nx-toast', type: 'success', message: 'Card glassmorphism reset to default.');
    }

    /**
     * Persist the config edited in the browser. Uploads are stored first and
     * their URLs merged in; PlatformTheme::save() clamps every tuning value.
     */
    public function save(array $config): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'image_light' => 'nullable|mimes:webp|max:900',
            'image_dark' => 'nullable|mimes:webp|max:900',
        ], [
            'image_light.mimes' => 'The wallpaper must be a .webp file.',
            'image_dark.mimes' => 'The wallpaper must be a .webp file.',
            'image_light.max' => 'Keep each wallpaper under 900 KB — it renders behind every page.',
            'image_dark.max' => 'Keep each wallpaper under 900 KB — it renders behind every page.',
        ]);

        $mode = in_array($config['mode'] ?? '', PlatformTheme::MODES, true) ? $config['mode'] : 'default';

        // Carry existing wallpaper URLs forward unless a new file replaces them.
        $imageLight = (string) ($config['image_light'] ?? $this->config['image_light'] ?? '');
        $imageDark = (string) ($config['image_dark'] ?? $this->config['image_dark'] ?? '');
        if ($this->image_light) {
            $imageLight = MediaStorage::storePublic($this->image_light, 'platform-theme');
        }
        if ($this->image_dark) {
            $imageDark = MediaStorage::storePublic($this->image_dark, 'platform-theme');
        }

        // If dark is kept linked, derive it from light so the two stay coherent.
        $light = $config['light'] ?? [];
        $dark = ! empty($config['customize_dark']) ? ($config['dark'] ?? []) : PlatformTheme::deriveDark($light);

        PlatformTheme::save([
            'mode' => $mode,
            'light' => $light,
            'dark' => $dark,
            'image_light' => $imageLight,
            'image_dark' => $imageDark,
        ]);

        // Refresh local state from the clamped, stored values.
        $this->reset('image_light', 'image_dark');
        $this->mount();
        Auditor::log('platform.theme_updated', payload: ['mode' => $mode]);
        $this->saved = 'Dashboard theme saved — live across the app.';
        $this->dispatch('nx-toast', type: 'success', message: 'Dashboard theme saved.');
    }

    /** Snap the whole platform theme back to the built-in default treatment. */
    public function resetAll(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        PlatformTheme::save(['mode' => 'default', 'light' => [], 'dark' => [], 'image_light' => '', 'image_dark' => '']);
        $this->mount();
        Auditor::log('platform.theme_reset');
        $this->saved = 'Dashboard theme reset to the platform default.';
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to default.');
    }

    public function render()
    {
        return view('livewire.admin.platform-theme', [
            'presets' => self::PRESETS,
            'extraPalette' => array_keys(PlatformTheme::EXTRA_PALETTE),
            'defaults' => PlatformTheme::TUNING_DEFAULTS,
        ]);
    }
}
