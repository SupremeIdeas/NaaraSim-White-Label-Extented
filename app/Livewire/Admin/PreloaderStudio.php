<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\PreloaderSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Preloader Studio (BLUEPRINT-preloader-studio §6). Assign any Studio
 * preset per page type, tune it (brand/manual colours, size, speed, opacity,
 * background, blur, loading text), and preview live. Entirely additive: nothing
 * changes on the storefront until an admin saves an assignment here.
 *
 * super_admin/admin only, re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class PreloaderStudio extends Component
{
    /** The page type currently being edited. */
    public string $type = 'default';

    /** Editable config fields for the selected page type. */
    public bool $enabled = true;

    public bool $inherit = false;

    public string $preset = 'simple-pulse';

    public bool $useBrandColor = true;

    public bool $useNeutral = false;

    public string $size = 'md';

    public float $speed = 1.0;

    public float $opacity = 1.0;

    public ?string $bgColor = null;

    public float $bgOpacity = 1.0;

    public bool $blur = false;

    public string $blurStyle = 'medium';

    public string $loadingText = 'Loading';

    /** Manual colour overrides (hex), indexed 0..N — only used when brand off. */
    public array $colors = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        // Batch 8: preloader customization is a tier-locked feature — a basic
        // white-label fork can't reach the Studio until it pays up. Inert on
        // the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_PRELOADER), 404);
    }

    public function mount(): void
    {
        $this->loadType('default');
    }

    /** Switch the edited page type, loading its stored assignment into the form. */
    public function loadType(string $type): void
    {
        $types = PreloaderSettings::pageTypes();
        $this->type = array_key_exists($type, $types) ? $type : 'default';

        $cfg = PreloaderSettings::forPageType($this->type);
        // A non-default type may be set to inherit; detect that from the raw row.
        $this->inherit = $this->type !== 'default' && $this->isInheriting($this->type);

        $this->enabled = (bool) ($cfg['enabled'] ?? true);
        // The Studio edits the new preset catalog. A legacy/unconfigured value
        // maps to its closest Studio analog (simple-pulse) so the gallery has a
        // selection and the preview is populated; nothing is persisted until Save.
        $this->preset = isset(PreloaderSettings::PRESETS[$cfg['preset']]) ? $cfg['preset'] : 'simple-pulse';
        $this->useBrandColor = (bool) ($cfg['use_brand_color'] ?? true);
        $this->useNeutral = (bool) ($cfg['use_neutral'] ?? false);
        $this->size = (string) ($cfg['size'] ?? 'md');
        $this->speed = (float) ($cfg['speed'] ?? 1.0);
        $this->opacity = (float) ($cfg['opacity'] ?? 1.0);
        $this->bgColor = $cfg['bg_color'] ?? null;
        $this->bgOpacity = (float) ($cfg['bg_opacity'] ?? 1.0);
        $this->blur = (bool) ($cfg['blur'] ?? false);
        $this->blurStyle = (string) ($cfg['blur_style'] ?? 'medium');
        $this->loadingText = (string) ($cfg['loading_text'] ?? 'Loading');
        $this->colors = array_values($cfg['colors'] ?? []);
        $this->saved = null;
    }

    public function selectPreset(string $slug): void
    {
        if (isset(PreloaderSettings::PRESETS[$slug]) || in_array($slug, PreloaderSettings::LEGACY_STYLES, true)) {
            $this->preset = $slug;
            $this->saved = null;
        }
    }

    public function save(): void
    {
        $this->validate([
            'preset' => 'required|string',
            'size' => 'required|string',
            'speed' => 'required|numeric|min:0.25|max:4',
            'opacity' => 'required|numeric|min:0|max:1',
            'bgOpacity' => 'required|numeric|min:0|max:1',
            'blurStyle' => 'required|in:light,medium,heavy',
            'loadingText' => 'nullable|string|max:24',
            'bgColor' => 'nullable|regex:/^#?[0-9a-fA-F]{3,6}$/',
            'colors.*' => 'nullable|regex:/^#?[0-9a-fA-F]{3,6}$/',
        ]);

        // Non-default types can inherit from default (everything else disabled).
        if ($this->type !== 'default' && $this->inherit) {
            PreloaderSettings::saveAssignment($this->type, ['inherit' => true]);
        } else {
            PreloaderSettings::saveAssignment($this->type, [
                'preset' => $this->preset,
                'enabled' => $this->enabled,
                'inherit' => false,
                'use_brand_color' => $this->useBrandColor,
                'use_neutral' => $this->useNeutral,
                'size' => $this->size,
                'speed' => round(max(0.25, min(4.0, $this->speed)), 2),
                'opacity' => round(max(0.0, min(1.0, $this->opacity)), 2),
                'bg_color' => $this->bgColor ?: null,
                'bg_opacity' => round(max(0.0, min(1.0, $this->bgOpacity)), 2),
                'blur' => $this->blur,
                'blur_style' => $this->blurStyle,
                'loading_text' => $this->loadingText ?: 'Loading',
                'colors' => array_values(array_filter($this->colors, fn ($c) => filled($c))),
            ]);
        }

        Auditor::log('preloader.assignment_updated', null, null, ['type' => $this->type, 'preset' => $this->preset]);
        $this->saved = $this->type;
        $this->dispatch('nx-toast', type: 'success', message: 'Preloader saved — live immediately.');
    }

    /** Build the live-preview config object from the current form state. */
    public function previewCfg(): array
    {
        return array_merge(PreloaderSettings::safeDefault(), [
            'preset' => $this->preset,
            'enabled' => true,
            'use_brand_color' => $this->useBrandColor,
            'use_neutral' => $this->useNeutral,
            'size' => $this->size,
            'speed' => $this->speed,
            'opacity' => $this->opacity,
            'bg_color' => $this->bgColor ?: null,
            'bg_opacity' => $this->bgOpacity,
            'blur' => $this->blur,
            'blur_style' => $this->blurStyle,
            'loading_text' => $this->loadingText ?: 'Loading',
            'colors' => array_values(array_filter($this->colors, fn ($c) => filled($c))),
        ]);
    }

    private function isInheriting(string $type): bool
    {
        // Reflect the raw stored row (forPageType() resolves inheritance away).
        try {
            $all = \App\Models\Setting::getValue('brand.preloader.assignments', []);
            $row = is_array($all) ? ($all[$type] ?? null) : null;

            return is_array($row) && ! empty($row['inherit']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function render()
    {
        return view('livewire.admin.preloader-studio', [
            'pageTypes' => PreloaderSettings::pageTypes(),
            'presets' => PreloaderSettings::PRESETS,
            'legacyStyles' => PreloaderSettings::LEGACY_STYLES,
            'selectedMeta' => PreloaderSettings::PRESETS[$this->preset] ?? null,
        ]);
    }
}
