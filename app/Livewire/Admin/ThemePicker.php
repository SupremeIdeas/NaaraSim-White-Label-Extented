<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\Auditor;
use App\Support\LandingHeroLibrary;
use App\Support\MediaStorage;
use App\Support\ThemePageLibrary;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * NAARA THEME SYSTEM — Batch 2 §4 (+ hero-image editing, owner request). Admin →
 * Theme picker. Select one of the 40 presets and apply it platform-wide, and —
 * per preset — upload/replace/remove the hero image shown on Dashboard, eSIM and
 * Numbers under that theme. No other inline token editing (colours/radius/type
 * stay seed-defined). Applying writes the slug to Setting, busts the theme cache,
 * and audits who/when — the next page load anywhere reflects it.
 *
 * Consistent with the other appearance admin screens (Dashboard theme, Branding):
 * same card grid, same confirm-before-commit seriousness this changes what every
 * logged-in user sees. Hero uploads follow Branding's own hero-art discipline
 * (WebP/JPG, capped small) so no theme ships a heavy image.
 */
#[Layout('components.layouts.admin')]
class ThemePicker extends Component
{
    use WithFileUploads;

    /** Entangled to <x-ui.modal> — its own close paths (X, backdrop, Escape)
     *  write a plain boolean here, so this is the only source of open/closed. */
    public bool $showHeroModal = false;

    /** Slug of the theme the modal is showing. Stays set after close (harmless
     *  — nothing reads it while $showHeroModal is false); editHero() always
     *  overwrites it before the modal opens. */
    public ?string $editingSlug = null;

    public string $editingName = '';

    /** Existing saved hero URL per surface for the theme being edited. */
    public array $currentHero = ['dashboard' => null, 'esim' => null, 'numbers' => null];

    // One optional upload per surface; blank = leave that surface untouched.
    public $hero_dashboard = null;

    public $hero_esim = null;

    public $hero_numbers = null;

    private const SURFACES = ['dashboard', 'esim', 'numbers'];

    /**
     * Swappable-section editor (owner request, 2026-09-07): "admin ... can
     * basically swap any header he likes to their existing theme ... and
     * also bottom nav." Entangled to <x-ui.modal> the same way as
     * $showHeroModal above.
     */
    public bool $showSectionsModal = false;

    public ?string $sectionEditingSlug = null;

    public string $sectionEditingName = '';

    /** Selected style key per swappable section, keyed exactly like SECTION_STYLE_ALLOW. */
    public array $sectionStyles = [
        'header' => 'default', 'bottom_nav' => 'default', 'login' => 'default', 'login_bg' => 'none', 'footer' => 'default',
    ];

    private const EDITABLE_SECTIONS = ['header', 'bottom_nav', 'login', 'login_bg', 'footer'];

    /** Human labels for the non-theme-keyed login_bg effect values. */
    private const LOGIN_BG_LABELS = [
        'none' => 'None', 'dot-grid' => 'Dot grid', 'mesh-grain' => 'Mesh grain', 'aurora' => 'Aurora',
    ];

    /**
     * Per-theme landing-page content editor (owner request, 2026-09-07):
     * "the existing themes were supposed to be able to help admin change
     * text images and the rest for that theme." Entirely schema-driven off
     * LandingHeroLibrary::fieldsFor() — a future landing style just adds an
     * entry there and this editor grows a matching form automatically, no
     * UI code change needed ("adopting the editor to also learn").
     */
    public bool $showLandingModal = false;

    public ?string $landingEditingSlug = null;

    public string $landingEditingName = '';

    /** Which landing_hero style is assigned to the theme being edited — decides which fields render. */
    public string $landingEditingStyle = 'default';

    /** Current field values, keyed exactly like that style's field schema. */
    public array $landingValues = [];

    /** Single image upload slot — blank leaves the saved image untouched (same partial-update discipline as hero images). */
    public $landing_image_upload = null;

    /**
     * Per-theme content-page editor — About / How It Works / Contact
     * (owner request, 2026-09-07: "for each theme, will and must carry its
     * own homepage, about us page, and 3 extra important page layouts...
     * our editor extended for super tweek as we are training it to have
     * this extended feature"). Same exact pattern as the landing editor
     * above, generalized across ThemePageLibrary::PAGES instead of a single
     * page — a future 4th/5th themed page needs no admin-UI code change,
     * only a new registry entry.
     */
    public bool $showPageModal = false;

    public ?string $pageEditingSlug = null;

    public string $pageEditingName = '';

    /** Which of ThemePageLibrary::PAGES is open — decides the field schema and the toast/audit copy. */
    public string $pageEditingPage = 'about_page';

    /** Which style is assigned to that page for the theme being edited — decides which fields render. */
    public string $pageEditingStyle = 'default';

    /** Current field values, keyed exactly like that page+style's field schema. */
    public array $pageValues = [];

    /**
     * Advanced colour override editor (owner request, 2026-09-07): "admin
     * Also change color pallet any any theme he picks... advance settings
     * to change each color with color code and save to override each
     * color then with a reset to default color." Works on ANY theme
     * (including naara-official) — colours are the one token family
     * already stored separately from section styles, so this is its own
     * modal rather than folding into Sections above.
     */
    public bool $showColorsModal = false;

    public ?string $colorEditingSlug = null;

    public string $colorEditingName = '';

    /** Hex strings keyed by ThemePreset::COLOR_KEYS — the CURRENT EFFECTIVE colour (override if set, else the seeded default), for the form. */
    public array $colorValues = [];

    /** Human labels for the admin form, in ThemePreset::COLOR_KEYS order. */
    private const COLOR_LABELS = [
        'primary' => 'Primary',
        'primary_dark' => 'Primary (dark)',
        'accent' => 'Accent',
        'accent_dark' => 'Accent (dark)',
        'navy' => 'Navy / dark surface',
        'action' => 'Action (destructive)',
    ];

    /**
     * Header editor (owner request, 2026-09-07): "full control" over the
     * header — a colour independent of the theme's own brand colours,
     * bottom-corner curve (10-40px), and glassmorphism depth — for ANY
     * theme, same "works on naara-official too" discipline as Colours
     * above. Same modal/property shape as Colours, so admins already
     * familiar with that editor recognise this one immediately.
     */
    public bool $showHeaderModal = false;

    public ?string $headerEditingSlug = null;

    public string $headerEditingName = '';

    /** The theme's resolved header style (e.g. 'default', 'aries-contrast') — decides whether the radius fields render at all. */
    public string $headerEditingStyle = 'default';

    /** '' means "no override, use this theme's own default header colour". */
    public string $headerBg = '';

    /**
     * The radius sliders only cover the valid 10-40px override range, so
     * "no override" (radius 0) can never be a slider POSITION — this
     * separate toggle is the actual on/off switch. Unchecked always omits
     * both radius keys on save, regardless of where the sliders sit.
     * headerBlur needs no equivalent: its full 0-24 range is meaningful
     * (0 is a real "no blur" choice, not a floor being clamped away), so
     * "leave it at this theme's own built-in default" is directly
     * reachable by leaving the slider where it started.
     */
    public bool $headerRoundBottom = false;

    public int $headerRadiusBl = ThemePreset::HEADER_RADIUS_MIN;

    public int $headerRadiusBr = ThemePreset::HEADER_RADIUS_MIN;

    public int $headerBlur = 0;

    public function mount(): void
    {
        $this->gate();
    }

    // Mirror EsimControlCenter's gate: role, or the delegable theme.manage
    // scope (registered in Batch 3 §6; role always suffices meanwhile).
    private function gate(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('theme.manage'),
            403,
        );
    }

    /** Open the hero-image editor for one theme, seeding it with what's saved. */
    public function editHero(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        $this->editingSlug = $slug;
        $this->editingName = $row->name;
        $this->currentHero = [
            'dashboard' => $assets['dashboard'] ?? null,
            'esim' => $assets['esim'] ?? null,
            'numbers' => $assets['numbers'] ?? null,
        ];
        $this->hero_dashboard = null;
        $this->hero_esim = null;
        $this->hero_numbers = null;
        $this->resetErrorBag();
        $this->showHeroModal = true;
    }

    /**
     * Save whichever surface uploads were provided for the theme being edited.
     * A blank surface is left exactly as it was — this is a partial update, not
     * a full replace, so editing one surface never clears the others.
     */
    public function saveHero(): void
    {
        $this->gate();

        if ($this->editingSlug === null) {
            return;
        }

        $this->validate([
            'hero_dashboard' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_esim' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_numbers' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ], [
            'hero_dashboard.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_esim.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_numbers.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dashboard.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_esim.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_numbers.max' => 'Keep the hero image under 600 KB for fast loading.',
        ]);

        $row = ThemePresetModel::where('slug', $this->editingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showHeroModal = false;

            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        $changed = false;
        foreach (self::SURFACES as $surface) {
            $field = 'hero_'.$surface;
            if ($this->{$field}) {
                $assets[$surface] = MediaStorage::storePublic($this->{$field}, 'theme-hero');
                $changed = true;
            }
        }

        if ($changed) {
            $row->hero_assets = $assets;
            $row->save();
            ThemePreset::bust(); // only matters if this is the active theme
            Auditor::log('theme.hero_updated', ThemePresetModel::class, $row->id, ['slug' => $this->editingSlug]);
        }

        $this->dispatch('nx-toast', type: 'success', message: $this->editingName.' hero images saved.');
        $this->showHeroModal = false;
    }

    /** Clear one surface's hero override for the theme being edited. */
    public function removeHeroSurface(string $surface): void
    {
        $this->gate();

        if ($this->editingSlug === null || ! in_array($surface, self::SURFACES, true)) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->editingSlug)->first();
        if ($row === null) {
            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        unset($assets[$surface]);
        $row->hero_assets = $assets;
        $row->save();

        $this->currentHero[$surface] = null;
        ThemePreset::bust();
        Auditor::log('theme.hero_removed', ThemePresetModel::class, $row->id, ['slug' => $this->editingSlug, 'surface' => $surface]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($surface).' hero image removed for '.$this->editingName.'.');
    }

    /**
     * Open the swappable-section editor for one theme, seeding it with
     * what's saved (defaulting any unset section to 'default').
     */
    public function editSections(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $saved = is_array($row->section_styles) ? $row->section_styles : [];
        $this->sectionEditingSlug = $slug;
        $this->sectionEditingName = $row->name;
        foreach (self::EDITABLE_SECTIONS as $section) {
            $neutral = ThemePreset::SECTION_STYLE_ALLOW[$section][0] ?? 'default';
            $this->sectionStyles[$section] = $saved[$section] ?? $neutral;
        }
        $this->showSectionsModal = true;
    }

    /**
     * Every style key a section may be set to, for the admin dropdown — this
     * IS the cross-theme swap: any theme (including naara-official) can
     * point its header/bottom_nav/login at ANY other theme's style family,
     * since the whitelist is a flat namespace shared across all 40 presets,
     * not scoped to "your own theme's styles only."
     *
     * @return array<string, array<string, string>> section => [key => label]
     */
    public function sectionStyleOptions(): array
    {
        $names = ThemePreset::all()->pluck('name', 'slug');
        $options = [];

        foreach (self::EDITABLE_SECTIONS as $section) {
            if ($section === 'login_bg') {
                $options[$section] = self::LOGIN_BG_LABELS;

                continue;
            }
            $options[$section] = collect(ThemePreset::SECTION_STYLE_ALLOW[$section] ?? ['default'])
                ->mapWithKeys(fn ($key) => [$key => $key === 'default' ? 'Default' : ($names[$key] ?? ucfirst($key))])
                ->all();
        }

        return $options;
    }

    /**
     * Persist the selected style per section. Server-side re-validated
     * against the SAME whitelist the resolver uses — a posted value outside
     * SECTION_STYLE_ALLOW[$section] is rejected, never written, exactly the
     * "never trust the posted value" discipline apply() already follows.
     */
    public function saveSections(): void
    {
        $this->gate();

        if ($this->sectionEditingSlug === null) {
            return;
        }

        foreach (self::EDITABLE_SECTIONS as $section) {
            $allow = ThemePreset::SECTION_STYLE_ALLOW[$section] ?? ['default'];
            if (! in_array($this->sectionStyles[$section] ?? $allow[0], $allow, true)) {
                $this->dispatch('nx-toast', type: 'error', message: 'Invalid section style selected.');

                return;
            }
        }

        $row = ThemePresetModel::where('slug', $this->sectionEditingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showSectionsModal = false;

            return;
        }

        $saved = is_array($row->section_styles) ? $row->section_styles : [];
        foreach (self::EDITABLE_SECTIONS as $section) {
            $saved[$section] = $this->sectionStyles[$section];
        }
        $row->section_styles = $saved;
        $row->save();

        ThemePreset::bust(); // only matters if this is the active theme
        Auditor::log('theme.sections_updated', ThemePresetModel::class, $row->id, [
            'slug' => $this->sectionEditingSlug,
            'sections' => $saved,
        ]);

        $this->dispatch('nx-toast', type: 'success', message: $this->sectionEditingName.' section styles saved.');
        $this->showSectionsModal = false;
    }

    /**
     * Open the landing-content editor for one theme. Only meaningful once a
     * custom landing_hero style is assigned via Sections above — a theme
     * still on 'default' uses the existing site-wide homepage content
     * (SiteContent/PageBuilder) instead, so there is nothing here to edit.
     */
    public function editLanding(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $sections = is_array($row->section_styles) ? $row->section_styles : [];
        $style = $sections['landing_hero'] ?? 'default';

        if ($style === 'default' || ! LandingHeroLibrary::has($style)) {
            $this->dispatch('nx-toast', type: 'error', message: $row->name.' doesn\'t have a custom landing page yet — assign one under Sections first.');

            return;
        }

        $saved = is_array($row->landing_content) ? $row->landing_content : [];
        $this->landingEditingSlug = $slug;
        $this->landingEditingName = $row->name;
        $this->landingEditingStyle = $style;
        $this->landingValues = [];
        foreach (LandingHeroLibrary::fieldsFor($style) as $field) {
            $this->landingValues[$field['key']] = $saved[$field['key']] ?? $field['default'];
        }
        $this->landing_image_upload = null;
        $this->resetErrorBag();
        $this->showLandingModal = true;
    }

    /** The field schema for the theme currently open in the landing editor — drives the dynamic form. */
    public function landingFields(): array
    {
        return LandingHeroLibrary::fieldsFor($this->landingEditingStyle);
    }

    /**
     * Persist the landing-content field values, re-validating every one
     * against its OWN field schema (never trust the posted value) — text
     * fields against their max length, selects against their declared
     * options, and the image field either keeps the saved URL or replaces
     * it with a freshly uploaded/validated one.
     */
    public function saveLanding(): void
    {
        $this->gate();

        if ($this->landingEditingSlug === null || ! LandingHeroLibrary::has($this->landingEditingStyle)) {
            return;
        }

        $fields = LandingHeroLibrary::fieldsFor($this->landingEditingStyle);
        $rules = [];
        foreach ($fields as $field) {
            $key = 'landingValues.'.$field['key'];
            $rules[$key] = match ($field['type']) {
                'select' => ['required', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                'image' => ['nullable'],
                default => ['required', 'string', 'max:'.($field['max'] ?? 255)],
            };
        }
        $rules['landing_image_upload'] = 'nullable|mimes:webp,jpg,jpeg|max:600';
        $this->validate($rules, [], collect($fields)->mapWithKeys(fn ($f) => ['landingValues.'.$f['key'] => $f['label']])->all());

        $row = ThemePresetModel::where('slug', $this->landingEditingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showLandingModal = false;

            return;
        }

        $content = is_array($row->landing_content) ? $row->landing_content : [];
        foreach ($fields as $field) {
            if ($field['type'] === 'image') {
                if ($this->landing_image_upload) {
                    $content[$field['key']] = MediaStorage::storePublic($this->landing_image_upload, 'theme-landing');
                }

                // else: leave whatever is already saved untouched.
                continue;
            }
            $content[$field['key']] = $this->landingValues[$field['key']];
        }

        $row->landing_content = $content;
        $row->save();

        ThemePreset::bust(); // only matters if this is the active theme
        Auditor::log('theme.landing_updated', ThemePresetModel::class, $row->id, [
            'slug' => $this->landingEditingSlug,
            'style' => $this->landingEditingStyle,
        ]);

        $this->dispatch('nx-toast', type: 'success', message: $this->landingEditingName.' landing page saved.');
        $this->showLandingModal = false;
    }

    /**
     * Open the content-page editor for one theme + page key. Only
     * meaningful once a custom style is assigned via Sections above — a
     * page still on 'default' uses the existing site-wide content
     * (SiteContent/PageBuilder) instead, so there is nothing here to edit.
     */
    public function editPage(string $slug, string $page): void
    {
        $this->gate();

        if (! in_array($page, ThemePageLibrary::PAGES, true)) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown page.');

            return;
        }

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $sections = is_array($row->section_styles) ? $row->section_styles : [];
        $style = $sections[$page] ?? 'default';

        if ($style === 'default' || ! ThemePageLibrary::has($page, $style)) {
            $this->dispatch('nx-toast', type: 'error', message: $row->name.' doesn\'t have a custom page here yet — assign one under Sections first.');

            return;
        }

        $saved = is_array($row->page_content) ? ($row->page_content[$page] ?? []) : [];
        $this->pageEditingSlug = $slug;
        $this->pageEditingName = $row->name;
        $this->pageEditingPage = $page;
        $this->pageEditingStyle = $style;
        $this->pageValues = [];
        foreach (ThemePageLibrary::fieldsFor($page, $style) as $field) {
            $this->pageValues[$field['key']] = $saved[$field['key']] ?? $field['default'];
        }
        $this->resetErrorBag();
        $this->showPageModal = true;
    }

    /** The field schema for the theme+page currently open in the page editor — drives the dynamic form. */
    public function pageFields(): array
    {
        return ThemePageLibrary::fieldsFor($this->pageEditingPage, $this->pageEditingStyle);
    }

    /**
     * Persist the page-content field values, re-validating every one
     * against its OWN field schema (never trust the posted value) — same
     * discipline as saveLanding(), nested one level deeper by page key.
     */
    public function savePage(): void
    {
        $this->gate();

        if ($this->pageEditingSlug === null || ! ThemePageLibrary::has($this->pageEditingPage, $this->pageEditingStyle)) {
            return;
        }

        $fields = ThemePageLibrary::fieldsFor($this->pageEditingPage, $this->pageEditingStyle);
        $rules = [];
        foreach ($fields as $field) {
            $key = 'pageValues.'.$field['key'];
            $rules[$key] = match ($field['type']) {
                'select' => ['required', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                'image' => ['nullable'],
                default => ['required', 'string', 'max:'.($field['max'] ?? 255)],
            };
        }
        $this->validate($rules, [], collect($fields)->mapWithKeys(fn ($f) => ['pageValues.'.$f['key'] => $f['label']])->all());

        $row = ThemePresetModel::where('slug', $this->pageEditingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showPageModal = false;

            return;
        }

        $allPages = is_array($row->page_content) ? $row->page_content : [];
        $content = $allPages[$this->pageEditingPage] ?? [];
        foreach ($fields as $field) {
            $content[$field['key']] = $this->pageValues[$field['key']];
        }
        $allPages[$this->pageEditingPage] = $content;
        $row->page_content = $allPages;
        $row->save();

        ThemePreset::bust(); // only matters if this is the active theme
        Auditor::log('theme.page_updated', ThemePresetModel::class, $row->id, [
            'slug' => $this->pageEditingSlug,
            'page' => $this->pageEditingPage,
            'style' => $this->pageEditingStyle,
        ]);

        $this->dispatch('nx-toast', type: 'success', message: $this->pageEditingName.' page saved.');
        $this->showPageModal = false;
    }

    /**
     * Open the advanced colour editor for one theme, seeding every field
     * with the CURRENT EFFECTIVE colour — an existing override if one is
     * saved, otherwise the theme's own seeded default — converted to hex
     * for the `<input type="color">` / text pairing in the form.
     */
    public function editColors(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $tokenColors = (is_array($row->tokens) ? $row->tokens['colors'] ?? [] : []);
        $overrides = is_array($row->color_overrides) ? $row->color_overrides : [];

        $this->colorEditingSlug = $slug;
        $this->colorEditingName = $row->name;
        $this->colorValues = [];
        foreach (ThemePreset::COLOR_KEYS as $key) {
            $effective = $overrides[$key] ?? $tokenColors[$key] ?? null;
            $this->colorValues[$key] = is_string($effective) ? ThemePreset::channelTripleToHex($effective) : '#000000';
        }
        $this->resetErrorBag();
        $this->showColorsModal = true;
    }

    /** Human labels for the colour form, in display order — schema-driven like every other editor here. */
    public function colorLabels(): array
    {
        return self::COLOR_LABELS;
    }

    /**
     * Persist every submitted colour as an override, re-validating each as
     * a strict 6-digit hex value server-side (never trust the posted
     * value) before converting to the "R G B" channel-triple format every
     * other colour token is stored in. Since the form always submits all
     * 6 whitelisted keys, this is a full replace of color_overrides, not a
     * partial merge — there is no other key that could exist there.
     */
    public function saveColors(): void
    {
        $this->gate();

        if ($this->colorEditingSlug === null) {
            return;
        }

        $rules = collect(ThemePreset::COLOR_KEYS)
            ->mapWithKeys(fn ($key) => ['colorValues.'.$key => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']])
            ->all();
        $this->validate($rules, [], collect(self::COLOR_LABELS)->mapWithKeys(fn ($label, $key) => ['colorValues.'.$key => $label])->all());

        $row = ThemePresetModel::where('slug', $this->colorEditingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showColorsModal = false;

            return;
        }

        // Only persist keys that actually differ from the theme's own seeded
        // default — otherwise a no-op save (or a reflexive Save click right
        // after "Reset all to default") would re-flag the theme as
        // customized and pin it to today's default value forever, even
        // though nothing was really changed.
        $tokenColors = is_array($row->tokens) ? $row->tokens['colors'] ?? [] : [];
        $overrides = [];
        foreach (ThemePreset::COLOR_KEYS as $key) {
            $triple = ThemePreset::hexToChannelTriple($this->colorValues[$key]);
            if ($triple !== null && $triple !== ($tokenColors[$key] ?? null)) {
                $overrides[$key] = $triple;
            }
        }

        $row->color_overrides = $overrides;
        $row->save();

        ThemePreset::bust(); // only matters if this is the active theme
        Auditor::log('theme.colors_updated', ThemePresetModel::class, $row->id, [
            'slug' => $this->colorEditingSlug,
        ]);

        $this->dispatch('nx-toast', type: 'success', message: $this->colorEditingName.' colours saved.');
        $this->showColorsModal = false;
    }

    /** Reset one colour back to the theme's seeded default — removes just that key from color_overrides. */
    public function resetColor(string $key): void
    {
        $this->gate();

        if ($this->colorEditingSlug === null || ! in_array($key, ThemePreset::COLOR_KEYS, true)) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->colorEditingSlug)->first();
        if ($row === null) {
            return;
        }

        $overrides = is_array($row->color_overrides) ? $row->color_overrides : [];
        unset($overrides[$key]);
        $row->color_overrides = $overrides;
        $row->save();

        $tokenColors = is_array($row->tokens) ? $row->tokens['colors'] ?? [] : [];
        $this->colorValues[$key] = isset($tokenColors[$key]) ? ThemePreset::channelTripleToHex($tokenColors[$key]) : '#000000';

        ThemePreset::bust();
        Auditor::log('theme.color_reset', ThemePresetModel::class, $row->id, ['slug' => $this->colorEditingSlug, 'key' => $key]);
        $this->dispatch('nx-toast', type: 'success', message: self::COLOR_LABELS[$key].' reset to default for '.$this->colorEditingName.'.');
    }

    /** Reset every colour on the theme being edited back to its seeded defaults in one action. */
    public function resetAllColors(): void
    {
        $this->gate();

        if ($this->colorEditingSlug === null) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->colorEditingSlug)->first();
        if ($row === null) {
            return;
        }

        $row->color_overrides = [];
        $row->save();

        $tokenColors = is_array($row->tokens) ? $row->tokens['colors'] ?? [] : [];
        foreach (ThemePreset::COLOR_KEYS as $key) {
            $this->colorValues[$key] = isset($tokenColors[$key]) ? ThemePreset::channelTripleToHex($tokenColors[$key]) : '#000000';
        }

        ThemePreset::bust();
        Auditor::log('theme.colors_reset', ThemePresetModel::class, $row->id, ['slug' => $this->colorEditingSlug]);
        $this->dispatch('nx-toast', type: 'success', message: 'All colours reset to default for '.$this->colorEditingName.'.');
    }

    /** Open the header editor for one theme, seeding it with the current effective values. */
    public function editHeader(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $style = ThemePreset::sectionStyleFor(is_array($row->section_styles) ? $row->section_styles : [], 'header');
        $settings = ThemePreset::resolveHeaderSettings(is_array($row->header_settings) ? $row->header_settings : [], $style);

        $this->headerEditingSlug = $slug;
        $this->headerEditingName = $row->name;
        $this->headerEditingStyle = $style;
        $this->headerBg = $settings['bg'] !== null ? ThemePreset::channelTripleToHex($settings['bg']) : '';
        $this->headerRoundBottom = $settings['radius_bl'] > 0 || $settings['radius_br'] > 0;
        $this->headerRadiusBl = $settings['radius_bl'] > 0 ? $settings['radius_bl'] : ThemePreset::HEADER_RADIUS_MIN;
        $this->headerRadiusBr = $settings['radius_br'] > 0 ? $settings['radius_br'] : ThemePreset::HEADER_RADIUS_MIN;
        $this->headerBlur = $settings['blur'];
        $this->resetErrorBag();
        $this->showHeaderModal = true;
    }

    /**
     * Persist the header form. Only writes a key that actually differs from
     * that theme's own default — same no-op-safe discipline as
     * saveColors(), so opening this editor and saving without changing
     * anything (or unchecking "round the bottom corners") never leaves a
     * phantom override behind.
     */
    public function saveHeader(): void
    {
        $this->gate();

        if ($this->headerEditingSlug === null) {
            return;
        }

        $rules = ['headerBg' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']];
        if ($this->headerRoundBottom) {
            $rules['headerRadiusBl'] = ['required', 'integer', 'min:'.ThemePreset::HEADER_RADIUS_MIN, 'max:'.ThemePreset::HEADER_RADIUS_MAX];
            $rules['headerRadiusBr'] = ['required', 'integer', 'min:'.ThemePreset::HEADER_RADIUS_MIN, 'max:'.ThemePreset::HEADER_RADIUS_MAX];
        }
        $rules['headerBlur'] = ['required', 'integer', 'min:'.ThemePreset::HEADER_BLUR_MIN, 'max:'.ThemePreset::HEADER_BLUR_MAX];
        $this->validate($rules, [], [
            'headerBg' => 'header colour', 'headerRadiusBl' => 'bottom-left curve', 'headerRadiusBr' => 'bottom-right curve', 'headerBlur' => 'glass depth',
        ]);

        $row = ThemePresetModel::where('slug', $this->headerEditingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showHeaderModal = false;

            return;
        }

        $settings = [];
        if ($this->headerBg !== '') {
            $settings['bg'] = ThemePreset::hexToChannelTriple($this->headerBg);
        }
        if ($this->headerRoundBottom) {
            $settings['radius_bl'] = $this->headerRadiusBl;
            $settings['radius_br'] = $this->headerRadiusBr;
        }
        $blurDefault = ThemePreset::resolveHeaderSettings([], $this->headerEditingStyle)['blur'];
        if ($this->headerBlur !== $blurDefault) {
            $settings['blur'] = $this->headerBlur;
        }

        $row->header_settings = $settings;
        $row->save();

        ThemePreset::bust();
        Auditor::log('theme.header_updated', ThemePresetModel::class, $row->id, ['slug' => $this->headerEditingSlug]);
        $this->dispatch('nx-toast', type: 'success', message: $this->headerEditingName.' header saved.');
        $this->showHeaderModal = false;
    }

    /** Reset a single header field ('bg'|'radius'|'blur') back to this theme's own default. */
    public function resetHeaderField(string $field): void
    {
        $this->gate();

        if ($this->headerEditingSlug === null || ! in_array($field, ['bg', 'radius', 'blur'], true)) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->headerEditingSlug)->first();
        if ($row === null) {
            return;
        }

        $settings = is_array($row->header_settings) ? $row->header_settings : [];
        if ($field === 'radius') {
            unset($settings['radius_bl'], $settings['radius_br']);
            $this->headerRoundBottom = false;
            $this->headerRadiusBl = ThemePreset::HEADER_RADIUS_MIN;
            $this->headerRadiusBr = ThemePreset::HEADER_RADIUS_MIN;
        } else {
            unset($settings[$field]);
            $resolved = ThemePreset::resolveHeaderSettings([], $this->headerEditingStyle);
            if ($field === 'bg') {
                $this->headerBg = '';
            } else {
                $this->headerBlur = $resolved['blur'];
            }
        }

        $row->header_settings = $settings;
        $row->save();

        ThemePreset::bust();
        Auditor::log('theme.header_field_reset', ThemePresetModel::class, $row->id, ['slug' => $this->headerEditingSlug, 'field' => $field]);
        $this->dispatch('nx-toast', type: 'success', message: 'Reset to default for '.$this->headerEditingName.'.');
    }

    /** Reset every header field on the theme being edited back to its defaults in one action. */
    public function resetAllHeader(): void
    {
        $this->gate();

        if ($this->headerEditingSlug === null) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->headerEditingSlug)->first();
        if ($row === null) {
            return;
        }

        $row->header_settings = [];
        $row->save();

        $resolved = ThemePreset::resolveHeaderSettings([], $this->headerEditingStyle);
        $this->headerBg = '';
        $this->headerRoundBottom = false;
        $this->headerRadiusBl = ThemePreset::HEADER_RADIUS_MIN;
        $this->headerRadiusBr = ThemePreset::HEADER_RADIUS_MIN;
        $this->headerBlur = $resolved['blur'];

        ThemePreset::bust();
        Auditor::log('theme.header_reset', ThemePresetModel::class, $row->id, ['slug' => $this->headerEditingSlug]);
        $this->dispatch('nx-toast', type: 'success', message: 'Header reset to default for '.$this->headerEditingName.'.');
    }

    /**
     * Apply a preset platform-wide. Rejects an unknown slug server-side (never
     * trust the posted value), writes the active-theme Setting, busts the cache,
     * and logs the change.
     */
    public function apply(string $slug): void
    {
        $this->gate();

        $exists = ThemePreset::all()->firstWhere('slug', $slug);
        if ($exists === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();
        Auditor::log('theme.applied', 'ThemePreset', null, ['slug' => $slug]);

        $this->dispatch('nx-toast', type: 'success', message: $exists['name'].' applied — it takes effect on the next page load.');
    }

    public function render()
    {
        return view('livewire.admin.theme-picker', [
            'presets' => ThemePreset::all(),
            'active' => ThemePreset::slug(),
        ]);
    }
}
