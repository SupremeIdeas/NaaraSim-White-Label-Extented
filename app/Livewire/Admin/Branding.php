<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\BrandSettings;
use App\Support\HeroBackground;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Branding (Module 26). Upload the logo set (PNG/JPG/WebP/SVG) and set
 * the brand name; everything re-brands with no redeploy. Each logo is stored on
 * the public disk via MediaStorage and its URL saved as a setting. The display
 * side (<x-brand-logo>) scales each logo responsibly (max-height + object-contain)
 * so any reasonable PNG looks right without pre-processing.
 */
#[Layout('components.layouts.admin')]
class Branding extends Component
{
    use WithFileUploads;

    public string $brand_name = '';

    /** White-label brand WORD — swapped into product/sub-brand names sitewide. */
    public string $brand_word = '';

    // Brand theme (Module 26) — hex colours + control roundness + preloader.
    public string $color_primary = '';

    public string $color_accent = '';

    public string $color_navy = '';

    public string $color_action = '';

    public string $radius = '0.5rem';

    public bool $preloader_enabled = false;

    public string $preloader_style = 'pulse-logo';

    // One upload slot per asset (all optional; blank = keep existing).
    // Naara family (umbrella) mark — home dashboard, marketing, Aurora welcome.
    public $family_light = null;

    public $family_dark = null;

    public $product_light = null;

    public $product_dark = null;

    public $agency_light = null;

    public $agency_dark = null;

    // Naara Gift storefront logo (its own sub-brand mark) — light + dark.
    public $gift_light = null;

    public $gift_dark = null;

    public $favicon = null;

    // Admin-tunable per-logo display scale (0.5–2.0; 1.0 = shipped size). Lets
    // the operator size each mark to taste with no code changes.
    public float $logo_scale_family = 1.0;

    public float $logo_scale_product = 1.0;

    public float $logo_scale_gift = 1.0;

    // Premium dashboard hero backgrounds (owner request) — light + dark, WebP/JPG.
    public $hero_light = null;

    public $hero_dark = null;

    // Dashboard-home description line under the hero title (BUILD-13 §3).
    public string $hero_description = '';

    // Admin on/off switch for the dashboard hero image (owner request).
    public bool $hero_enabled = true;

    // Dashboard hero headline — overridable + resizable (owner request). Blank
    // title falls back to the shipped "My Connectivity".
    public string $hero_title = '';

    public string $hero_title_size = HeroBackground::DEFAULT_TITLE_SIZE;

    public string $hero_cta_size = HeroBackground::DEFAULT_CTA_SIZE;

    // Site-wide font system (owner request): '' keeps the shipped Naara default
    // (Supreme Display / Didact Gothic); 'google' picks a Google Font by name;
    // 'custom' uses an uploaded web font file. One source per slot.
    public string $font_display_source = '';

    public string $font_display_google = '';

    public $font_display_custom = null;

    public string $font_sans_source = '';

    public string $font_sans_google = '';

    public $font_sans_custom = null;

    public ?string $saved = null;

    /** field => setting key. */
    private const SLOTS = [
        'family_light' => 'brand.logo_family_light',
        'family_dark' => 'brand.logo_family_dark',
        'product_light' => 'brand.logo_product_light',
        'product_dark' => 'brand.logo_product_dark',
        'agency_light' => 'brand.logo_agency_light',
        'agency_dark' => 'brand.logo_agency_dark',
        'gift_light' => 'brand.logo_gift_light',
        'gift_dark' => 'brand.logo_gift_dark',
        'favicon' => 'brand.favicon',
        'hero_light' => HeroBackground::LIGHT_KEY,
        'hero_dark' => HeroBackground::DARK_KEY,
    ];

    public function mount(): void
    {
        $this->brand_name = BrandSettings::name();
        $this->brand_word = BrandSettings::word();
        $this->color_primary = BrandSettings::color('primary');
        $this->color_accent = BrandSettings::color('accent');
        $this->color_navy = BrandSettings::color('navy');
        $this->color_action = BrandSettings::color('action');
        $this->radius = BrandSettings::radius();
        $this->preloader_enabled = BrandSettings::preloaderEnabled();
        $this->preloader_style = BrandSettings::preloaderStyle();
        $this->hero_description = HeroBackground::description();
        $this->hero_enabled = HeroBackground::enabled();
        $this->hero_title = HeroBackground::title();
        $this->hero_title_size = HeroBackground::titleSize();
        $this->hero_cta_size = HeroBackground::ctaSize();
        $this->logo_scale_family = BrandSettings::logoScale('family');
        $this->logo_scale_product = BrandSettings::logoScale('product');
        $this->logo_scale_gift = BrandSettings::logoScale('gift');
        $this->font_display_source = BrandSettings::fontSource('display');
        $this->font_display_google = BrandSettings::googleFontName('display') ?? '';
        $this->font_sans_source = BrandSettings::fontSource('sans');
        $this->font_sans_google = BrandSettings::googleFontName('sans') ?? '';
    }

    /** Save the brand theme (colours, roundness, preloader). Takes effect live. */
    public function saveTheme(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'brand_word' => 'required|string|max:40|regex:/^[\pL\pN][\pL\pN .&\'-]*$/u',
            'color_primary' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_accent' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_navy' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_action' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'radius' => 'required|in:0rem,0.25rem,0.5rem,0.75rem,1rem',
            'preloader_style' => 'required|in:'.implode(',', BrandSettings::PRELOADER_STYLES),
        ], [
            'brand_word.regex' => 'Use a plain brand word (letters, numbers, spaces).',
            'color_primary.regex' => 'Use a 6-digit hex colour like #0A6E6E.',
            'color_accent.regex' => 'Use a 6-digit hex colour like #D4A017.',
            'color_navy.regex' => 'Use a 6-digit hex colour like #0D1B2A.',
            'color_action.regex' => 'Use a 6-digit hex colour like #E8412A.',
        ]);

        Setting::setValue('brand.word', trim($this->brand_word), 'brand');
        Setting::setValue('brand.color_primary', $this->color_primary, 'brand');
        Setting::setValue('brand.color_accent', $this->color_accent, 'brand');
        Setting::setValue('brand.color_navy', $this->color_navy, 'brand');
        Setting::setValue('brand.color_action', $this->color_action, 'brand');
        Setting::setValue('brand.radius', $this->radius, 'brand');
        Setting::setValue('brand.preloader_enabled', $this->preloader_enabled, 'brand');
        Setting::setValue('brand.preloader_style', $this->preloader_style, 'brand');

        BrandSettings::flush();
        Auditor::log('brand.theme_updated');
        $this->saved = 'Brand theme saved — the new colours are live across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Brand theme saved.');
    }

    /**
     * Apply one of the 25 curated palettes into the colour fields (live preview
     * via wire:model) — the admin still clicks "Save theme" to persist it, so
     * they can audition presets without committing.
     */
    public function applyPalette(string $name): void
    {
        $palette = BrandSettings::PALETTES[$name] ?? null;
        if ($palette === null) {
            return;
        }
        $this->color_primary = $palette['primary'];
        $this->color_accent = $palette['accent'];
        $this->color_navy = $palette['navy'];
        $this->color_action = $palette['action'];
        $this->dispatch('nx-toast', type: 'success', message: "“{$name}” loaded — click Save theme to apply it.");
    }

    /** Reset colours + roundness to the shipped brand defaults. */
    public function resetTheme(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach (['brand.color_primary', 'brand.color_accent', 'brand.color_navy', 'brand.color_action', 'brand.radius'] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        BrandSettings::flush();
        $this->color_primary = BrandSettings::color('primary');
        $this->color_accent = BrandSettings::color('accent');
        $this->color_navy = BrandSettings::color('navy');
        $this->color_action = BrandSettings::color('action');
        $this->radius = BrandSettings::radius();
        Auditor::log('brand.theme_reset');
        $this->saved = 'Brand colours reset to the NaaraSim defaults.';
    }

    /**
     * Save the site-wide font system (owner request): a Google Font or an
     * uploaded custom font, per slot (title/display + body/sans). Leaving a
     * slot's source blank keeps the shipped Naara default untouched.
     */
    public function saveFonts(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'font_display_source' => 'nullable|in:google,custom',
            'font_sans_source' => 'nullable|in:google,custom',
            'font_display_google' => 'nullable|string|max:60|regex:/^[A-Za-z0-9 ]+$/',
            'font_sans_google' => 'nullable|string|max:60|regex:/^[A-Za-z0-9 ]+$/',
            'font_display_custom' => 'nullable|mimes:woff2,woff,ttf,otf|max:2048',
            'font_sans_custom' => 'nullable|mimes:woff2,woff,ttf,otf|max:2048',
        ], [
            'font_display_google.regex' => 'Use a plain Google Font name (letters, numbers, spaces).',
            'font_sans_google.regex' => 'Use a plain Google Font name (letters, numbers, spaces).',
            'font_display_custom.mimes' => 'Upload a woff2, woff, ttf, or otf font file.',
            'font_sans_custom.mimes' => 'Upload a woff2, woff, ttf, or otf font file.',
        ]);

        foreach (['display', 'sans'] as $slot) {
            $sourceField = "font_{$slot}_source";
            $googleField = "font_{$slot}_google";
            $customField = "font_{$slot}_custom";
            $source = $this->{$sourceField};

            if ($source === 'google') {
                if (trim($this->{$googleField}) === '') {
                    $this->addError($googleField, 'Enter a Google Font name.');

                    return;
                }
                Setting::setValue("brand.font_{$slot}_source", 'google', 'brand');
                Setting::setValue("brand.font_{$slot}_google", trim($this->{$googleField}), 'brand');
                Setting::where('key', "brand.font_{$slot}_custom")->get()->each->delete();
            } elseif ($source === 'custom') {
                if ($this->{$customField}) {
                    $url = MediaStorage::storePublic($this->{$customField}, 'fonts');
                    Setting::setValue("brand.font_{$slot}_custom", $url, 'brand');
                    $this->{$customField} = null;
                } elseif (BrandSettings::customFontUrl($slot) === null) {
                    $this->addError($customField, 'Upload a font file.');

                    return;
                }
                Setting::setValue("brand.font_{$slot}_source", 'custom', 'brand');
                Setting::where('key', "brand.font_{$slot}_google")->get()->each->delete();
            } else {
                foreach (["brand.font_{$slot}_source", "brand.font_{$slot}_google", "brand.font_{$slot}_custom"] as $key) {
                    Setting::where('key', $key)->get()->each->delete();
                }
            }
        }

        BrandSettings::flush();
        Auditor::log('brand.fonts_updated');
        $this->saved = 'Font settings saved — live across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Fonts saved.');
    }

    /** Reset both font slots back to the shipped Naara defaults. */
    public function resetFonts(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach (['display', 'sans'] as $slot) {
            foreach (["brand.font_{$slot}_source", "brand.font_{$slot}_google", "brand.font_{$slot}_custom"] as $key) {
                Setting::where('key', $key)->get()->each->delete();
            }
        }
        BrandSettings::flush();
        $this->font_display_source = '';
        $this->font_display_google = '';
        $this->font_display_custom = null;
        $this->font_sans_source = '';
        $this->font_sans_google = '';
        $this->font_sans_custom = null;
        Auditor::log('brand.fonts_reset');
        $this->saved = 'Fonts reset to the Naara defaults.';
        $this->dispatch('nx-toast', type: 'success', message: 'Fonts reset.');
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'brand_name' => 'required|string|max:60',
            'family_light' => 'nullable|image|max:2048',
            'family_dark' => 'nullable|image|max:2048',
            'product_light' => 'nullable|image|max:2048',
            'product_dark' => 'nullable|image|max:2048',
            'agency_light' => 'nullable|image|max:2048',
            'agency_dark' => 'nullable|image|max:2048',
            'gift_light' => 'nullable|image|max:2048',
            'gift_dark' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            // Hero art: WebP or JPG only, kept small for fast in-app loading.
            'hero_light' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_dark' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_description' => 'nullable|string|max:120',
            'hero_title' => 'nullable|string|max:40',
            'hero_title_size' => 'required|in:'.implode(',', array_keys(HeroBackground::TITLE_SIZES)),
            'hero_cta_size' => 'required|in:'.implode(',', array_keys(HeroBackground::CTA_SIZES)),
            'logo_scale_family' => 'numeric|min:0.5|max:2',
            'logo_scale_product' => 'numeric|min:0.5|max:2',
            'logo_scale_gift' => 'numeric|min:0.5|max:2',
        ], [
            'hero_light.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dark.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_light.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_dark.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_description.max' => 'Keep the dashboard description to one short line (120 characters).',
            'hero_title.max' => 'Keep the hero title short (40 characters) so it still fits the layout.',
        ]);

        Setting::setValue('brand.name', trim($this->brand_name), 'brand');

        foreach (self::SLOTS as $field => $key) {
            if ($this->{$field}) {
                $url = MediaStorage::storePublic($this->{$field}, 'brand');
                Setting::setValue($key, $url, 'brand');
                $this->{$field} = null;
            }
        }

        // Dashboard-home description (BUILD-13 §3). Blank clears back to the
        // sensible default (HeroBackground::description() never returns empty).
        Setting::setValue(HeroBackground::DESC_KEY, trim($this->hero_description), 'brand');

        // On/off switch for the dashboard hero image (owner request).
        Setting::setValue(HeroBackground::ENABLED_KEY, $this->hero_enabled, 'brand');

        // Hero headline override + size (owner request). Blank clears back to
        // the shipped "My Connectivity" (HeroBackground::title() never returns
        // empty).
        Setting::setValue(HeroBackground::TITLE_KEY, trim($this->hero_title), 'brand');
        Setting::setValue(HeroBackground::TITLE_SIZE_KEY, $this->hero_title_size, 'brand');

        // CTA (Buy eSIM / Get Number) button size override (owner request).
        Setting::setValue(HeroBackground::CTA_SIZE_KEY, $this->hero_cta_size, 'brand');

        // Per-logo display scale (admin taste) — clamped 0.5–2.0.
        Setting::setValue('brand.logo_scale_family', max(0.5, min(2.0, (float) $this->logo_scale_family)), 'brand');
        Setting::setValue('brand.logo_scale_product', max(0.5, min(2.0, (float) $this->logo_scale_product)), 'brand');
        Setting::setValue('brand.logo_scale_gift', max(0.5, min(2.0, (float) $this->logo_scale_gift)), 'brand');

        BrandSettings::flush();
        HeroBackground::flush();
        $this->hero_description = HeroBackground::description(); // reflect resolved default if blank
        $this->hero_title = HeroBackground::title(); // reflect resolved default if blank
        Auditor::log('brand.updated');
        $this->saved = 'Branding saved. Your logo and name now show across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Branding saved — live everywhere.');
    }

    /** Remove the hero backgrounds — the dashboard hero returns to its default. */
    public function removeHero(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach ([HeroBackground::LIGHT_KEY, HeroBackground::DARK_KEY] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        HeroBackground::flush();
        Auditor::log('brand.hero_removed');
        $this->saved = 'Hero backgrounds removed — the dashboard uses the default heading.';
    }

    public function render()
    {
        return view('livewire.admin.branding', [
            'brand' => BrandSettings::current(),
        ]);
    }
}
