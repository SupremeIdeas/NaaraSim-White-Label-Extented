<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\CustomPreloader;
use App\Support\PreloaderSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

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

    /**
     * The admin's own animation per mode (preset = 'custom'): ['light' => meta, 'dark' => meta]. Files are validated by content and stored the
     * moment they are chosen; the assignment itself is only written on Save.
     */
    public array $custom = [];

    /** Temporary Livewire uploads (one per mode). */
    public $uploadLight = null;

    public $uploadDark = null;

    public ?string $customError = null;

    /** Files uploaded in this session but never saved (deleted if replaced/removed, so the store does not collect orphans). */
    public array $pendingFiles = [];

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
        $this->custom = CustomPreloader::clean($cfg['custom'] ?? []);
        $this->pendingFiles = [];
        $this->customError = null;
        $this->preset = ($cfg['preset'] === PreloaderSettings::CUSTOM && $this->custom !== [])
            ? PreloaderSettings::CUSTOM
            : (isset(PreloaderSettings::PRESETS[$cfg['preset']]) ? $cfg['preset'] : 'simple-pulse');
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
        // availablePresets() (not the raw PRESETS catalog) so a fork can't
        // select a master-only premium preset via a direct component call
        // that skips the picker UI where it's already hidden.
        if (isset(PreloaderSettings::availablePresets()[$slug]) || in_array($slug, PreloaderSettings::LEGACY_STYLES, true) || $slug === PreloaderSettings::CUSTOM) {
            $this->preset = $slug;
            $this->saved = null;
        }
    }

    public function updatedUploadLight(): void
    {
        $this->receiveUpload('light', $this->uploadLight);
        $this->uploadLight = null;
    }

    public function updatedUploadDark(): void
    {
        $this->receiveUpload('dark', $this->uploadDark);
        $this->uploadDark = null;
    }

    /** Validate (by content) and store an upload for one mode, replacing that mode's previous file. */
    private function receiveUpload(string $variant, $file): void
    {
        $this->customError = null;
        if (! $file) {
            return;
        }
        try {
            $this->validate(['upload'.ucfirst($variant) => ['file', 'extensions:gif,webp,json', 'max:'.CustomPreloader::MAX_IMAGE_KB]]);
            $meta = CustomPreloader::store($file);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->customError = collect($e->errors())->flatten()->first();

            return;
        } catch (\InvalidArgumentException $e) {
            $this->customError = $e->getMessage();

            return;
        }

        $this->dropUnsaved($this->custom[$variant] ?? null);
        $this->custom[$variant] = $meta;
        $this->pendingFiles[] = $meta['path'];
        $this->preset = PreloaderSettings::CUSTOM;
        $this->saved = null;
    }

    public function removeCustom(string $variant): void
    {
        if (! in_array($variant, CustomPreloader::VARIANTS, true)) {
            return;
        }
        $this->dropUnsaved($this->custom[$variant] ?? null);
        unset($this->custom[$variant]);
        $this->customError = null;
        $this->saved = null;
    }

    /** Delete a file only if it was uploaded this session and never saved. */
    private function dropUnsaved(?array $meta): void
    {
        if ($meta && in_array($meta['path'] ?? '', $this->pendingFiles, true)) {
            CustomPreloader::delete($meta);
            $this->pendingFiles = array_values(array_diff($this->pendingFiles, [$meta['path']]));
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

        // Same allow-list as selectPreset() — belt-and-suspenders against a
        // fork saving a master-only premium preset by any other path.
        abort_unless(
            isset(PreloaderSettings::availablePresets()[$this->preset]) || in_array($this->preset, PreloaderSettings::LEGACY_STYLES, true) || $this->preset === PreloaderSettings::CUSTOM,
            403,
        );
        if ($this->preset === PreloaderSettings::CUSTOM && CustomPreloader::clean($this->custom) === []) {
            $this->addError('preset', 'Upload a GIF, WebP or Lottie file first (for light mode, dark mode, or both).');

            return;
        }
        $previous = CustomPreloader::clean(PreloaderSettings::forPageType($this->type)['custom'] ?? []);

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
                'custom' => CustomPreloader::clean($this->custom),
            ]);
        }

        // The saved files are now referenced; free the ones this page type no longer uses.
        $kept = collect(CustomPreloader::clean($this->custom))->pluck('path')->all();
        foreach ($previous as $meta) {
            if (! in_array($meta['path'], $kept, true) && ! $this->referencedElsewhere($meta['path'])) {
                CustomPreloader::delete($meta);
            }
        }
        $this->pendingFiles = [];

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
            'custom' => CustomPreloader::clean($this->custom),
        ]);
    }

    /** True if another page type's saved assignment still points at this file. */
    private function referencedElsewhere(string $path): bool
    {
        try {
            $all = \App\Models\Setting::getValue('brand.preloader.assignments', []);
        } catch (\Throwable) {
            return true; // unknown → never delete
        }
        foreach ((array) $all as $type => $row) {
            if ($type === $this->type || ! is_array($row)) {
                continue;
            }
            foreach (CustomPreloader::clean($row['custom'] ?? []) as $meta) {
                if ($meta['path'] === $path) {
                    return true;
                }
            }
        }

        return false;
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
            'presets' => PreloaderSettings::availablePresets(),
            'legacyStyles' => PreloaderSettings::LEGACY_STYLES,
            'selectedMeta' => PreloaderSettings::PRESETS[$this->preset] ?? null,
            'isCustom' => $this->preset === PreloaderSettings::CUSTOM,
            'customAccept' => CustomPreloader::ACCEPT,
            'customMaxImageKb' => CustomPreloader::MAX_IMAGE_KB,
            'customMaxLottieKb' => CustomPreloader::MAX_LOTTIE_KB,
        ]);
    }
}
