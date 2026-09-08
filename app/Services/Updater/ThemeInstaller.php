<?php

namespace App\Services\Updater;

use App\Models\ThemePreset as ThemePresetModel;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ThemePreset;
use App\Support\UpdateManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Installs a theme `.naaraupdate` package (Batch 3). Reuses Batch 1's package
 * format and trust gate unchanged — a theme package IS a `.naaraupdate` file,
 * just with `package_type: "theme"` and a `payload/theme.json` instead of code.
 *
 * Deliberately NOT built on Batch 2's UpdateApplier: per direct inspection of
 * App\Support\ThemePreset, a theme is PAINT, never plumbing — its render path
 * (emitVars()) only emits a small, strictly-whitelisted set of CSS variables and
 * silently drops anything that doesn't validate, so even a maliciously-crafted
 * theme cannot inject CSS or execute code. Installing one is a same-request
 * database write (one `theme_presets` row) plus a few file copies — closer to
 * "save a settings form" than "deploy code" — so this class carries its own
 * right-sized safety net (a try/catch that cleans up any partially-copied asset
 * files) rather than Batch 2's maintenance-mode/backup/health-check/rollback
 * pipeline, which would be pure overhead here.
 *
 * `preview()` verifies and validates without writing anything, for the admin
 * confirmation screen; `install()` re-verifies and re-validates from scratch
 * (never trusts a prior preview call) before writing.
 */
class ThemeInstaller
{
    public function __construct(private readonly PackageVerifier $verifier)
    {
    }

    /** Verify + validate a theme package without touching storage or the database. */
    public function preview(string $packagePath): ThemeInstallPreview
    {
        $manifest = $this->verifyThemePackage($packagePath);
        $theme = $this->readThemeJson($packagePath);
        $this->validateShape($theme);

        $swatches = [];
        foreach (($theme['tokens']['colors'] ?? []) as $key => $value) {
            // Only ever pass ALREADY-VALIDATED colour values to the preview —
            // this feeds an inline `style` attribute, so an unvalidated value
            // here would be a CSS-injection risk into the admin's own browser,
            // not just a cosmetic glitch.
            if (is_string($key) && ThemePreset::isValidColorTriple($value)) {
                $swatches[$key] = $value;
            }
        }

        return new ThemeInstallPreview(
            slug: $theme['slug'],
            name: $theme['name'],
            persona: $theme['persona'] ?? null,
            colorSwatches: $swatches,
            iconFamily: $theme['icon_family'],
            layoutVariants: is_array($theme['layout_variants'] ?? null) ? $theme['layout_variants'] : [],
            fontWarnings: $this->fontWarnings($theme),
            packageId: $manifest->packageId,
            version: $manifest->version,
        );
    }

    /**
     * Verify, validate, copy assets, and upsert the theme_presets row. Throws
     * on any rejection (bad signature, wrong package type, invalid shape, a
     * built-in slug collision, an asset that can't be copied or wouldn't
     * render) — nothing partial is left behind on failure.
     */
    public function install(string $packagePath, ?int $initiatedByUserId = null): ThemePresetModel
    {
        $manifest = $this->verifyThemePackage($packagePath);
        $theme = $this->readThemeJson($packagePath);
        $this->validateShape($theme);
        $fontWarnings = $this->fontWarnings($theme);

        $slug = $theme['slug'];
        $existing = ThemePresetModel::where('slug', $slug)->first();

        $copiedPaths = [];

        try {
            $heroAssets = $this->copyAssets(
                $packagePath,
                $slug,
                is_array($theme['hero_assets'] ?? null) ? $theme['hero_assets'] : [],
                $copiedPaths,
            );

            $row = DB::transaction(fn () => ThemePresetModel::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $theme['name'],
                    'persona' => $theme['persona'] ?? null,
                    'tokens' => is_array($theme['tokens'] ?? null) ? $theme['tokens'] : [],
                    'icon_family' => $theme['icon_family'],
                    'hero_assets' => $heroAssets,
                    'layout_variants' => is_array($theme['layout_variants'] ?? null) ? $theme['layout_variants'] : [],
                    // Never settable from an upload — is_built_in describes only
                    // the themes shipped with the platform itself.
                    'is_built_in' => false,
                    // Preserve admin-set ordering across an update; only take the
                    // package's value on first install.
                    'sort_order' => $existing?->sort_order ?? (int) ($theme['sort_order'] ?? 0),
                ],
            ));
        } catch (\Throwable $e) {
            $this->cleanupAssets($copiedPaths);
            throw $e;
        }

        ThemePreset::bust();
        Auditor::log('theme.installed', ThemePresetModel::class, $row->id, [
            'slug' => $slug,
            'source_package_id' => $manifest->packageId,
            'font_warnings' => $fontWarnings,
        ]);

        return $row;
    }

    private function verifyThemePackage(string $packagePath): UpdateManifest
    {
        $result = $this->verifier->verify($packagePath);
        if (! $result->passed) {
            throw new \RuntimeException('Theme package rejected: '.$result->reason);
        }
        if ($result->manifest->packageType !== 'theme') {
            throw new \RuntimeException("This package is type '{$result->manifest->packageType}', not a theme package.");
        }

        return $result->manifest;
    }

    /** @return array<string,mixed> */
    private function readThemeJson(string $packagePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            throw new \RuntimeException('Could not open theme package.');
        }

        try {
            $bytes = $zip->getFromName(PackageVerifier::PAYLOAD_PREFIX.'theme.json');
            if ($bytes === false) {
                throw new \RuntimeException('Theme package has no payload/theme.json.');
            }

            $decoded = json_decode($bytes, true);
            if (! is_array($decoded)) {
                throw new \RuntimeException('theme.json is not valid JSON.');
            }

            return $decoded;
        } finally {
            $zip->close();
        }
    }

    /**
     * Validate theme.json against ThemePreset's own real expectations, BEFORE
     * touching the database — rejecting (never silently dropping) anything
     * that would be an admin's obvious mistake: a malformed slug, a slug
     * collision with a built-in theme, an out-of-range icon family, or a
     * layout_variants value outside the three real structural partials.
     *
     * @param  array<string,mixed>  $data
     */
    private function validateShape(array $data): void
    {
        $slug = $data['slug'] ?? null;
        if (! is_string($slug) || $slug === '' || strlen($slug) > 60 || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
            $shown = is_string($slug) ? $slug : gettype($slug);
            throw new \RuntimeException("Invalid theme slug '{$shown}' — expected lowercase letters, digits and hyphens (e.g. 'naara-line-persona').");
        }

        if (ThemePresetModel::where('slug', $slug)->where('is_built_in', true)->exists()) {
            throw new \RuntimeException("'{$slug}' is a built-in platform theme and cannot be installed over from an uploaded package.");
        }

        $name = $data['name'] ?? null;
        if (! is_string($name) || $name === '' || strlen($name) > 255) {
            throw new \RuntimeException('Theme name is required and must be 255 characters or fewer.');
        }

        if (isset($data['persona']) && (! is_string($data['persona']) || strlen($data['persona']) > 255)) {
            throw new \RuntimeException('Theme persona must be a string of 255 characters or fewer.');
        }

        $icon = $data['icon_family'] ?? null;
        if (! is_array($icon)
            || ! in_array($icon['style'] ?? null, ThemePreset::ICON_STYLES, true)
            || ! is_string($icon['set'] ?? null)
            || preg_match(ThemePreset::ICON_SET_PATTERN, $icon['set']) !== 1) {
            throw new \RuntimeException(
                "Invalid icon_family — 'style' must be one of [".implode(', ', ThemePreset::ICON_STYLES)."] and 'set' must be 1-40 lowercase letters/digits/hyphens."
            );
        }

        foreach ((is_array($data['layout_variants'] ?? null) ? $data['layout_variants'] : []) as $page => $variant) {
            if (! in_array($variant, ThemePreset::VARIANTS, true)) {
                $shown = is_scalar($variant) ? (string) $variant : gettype($variant);
                throw new \RuntimeException("layout_variants.{$page} = '{$shown}' is not one of: ".implode(', ', ThemePreset::VARIANTS).'.');
            }
        }

        if (isset($data['tokens']) && ! is_array($data['tokens'])) {
            throw new \RuntimeException('tokens must be an object.');
        }
    }

    /**
     * Every `tokens.typography.*` value not in the CURRENT font allow-list gets
     * a clear, actionable warning — the theme still installs and applies
     * everything else, but this one override will silently do nothing at
     * render time (emitVars()'s own allow-list enforcement) until the font is
     * added to the platform. Surfacing that now, at install time, turns a
     * silent rendering gap into an understood one.
     *
     * @param  array<string,mixed>  $data
     * @return list<string>
     */
    private function fontWarnings(array $data): array
    {
        $allow = ThemePreset::fontAllowList();
        $warnings = [];

        foreach ((is_array($data['tokens']['typography'] ?? null) ? $data['tokens']['typography'] : []) as $slot => $font) {
            if (is_string($font) && ! in_array($font, $allow, true)) {
                $warnings[] = "This theme requests '{$font}' for {$slot} typography, which isn't in the platform's approved font list yet — the theme will install and apply, but that font override won't render until '{$font}' is added to the font allow-list in a code update.";
            }
        }

        return $warnings;
    }

    /**
     * Copy every referenced hero asset from payload/assets/ into public storage
     * under themes/{slug}/, then validate the resulting URL against the EXACT
     * same pattern ThemePreset::heroFor() checks at render time — rejecting the
     * whole install (not silently saving a hero that will never display) if a
     * referenced asset is missing from the package or its URL wouldn't render.
     *
     * @param  array<string,mixed>  $heroAssetRefs  surface => payload-relative path
     * @param  list<string>  $copiedPaths  OUT — every disk path written this call, for cleanup on failure
     * @return array<string,string>  surface => final public URL
     */
    private function copyAssets(string $packagePath, string $slug, array $heroAssetRefs, array &$copiedPaths): array
    {
        if ($heroAssetRefs === []) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            throw new \RuntimeException('Could not reopen theme package to copy assets.');
        }

        $disk = MediaStorage::disk();
        $result = [];

        try {
            foreach ($heroAssetRefs as $surface => $ref) {
                if (! is_string($surface) || ! is_string($ref) || $ref === '') {
                    throw new \RuntimeException("hero_assets entry for '{$surface}' is invalid.");
                }

                $bytes = $zip->getFromName(PackageVerifier::PAYLOAD_PREFIX.$ref);
                if ($bytes === false) {
                    throw new \RuntimeException("hero_assets.{$surface} references '{$ref}', which isn't in the package.");
                }

                $ext = strtolower(pathinfo($ref, PATHINFO_EXTENSION) ?: 'bin');
                $path = "themes/{$slug}/{$surface}.{$ext}";

                Storage::disk($disk)->put($path, $bytes, 'public');
                $copiedPaths[] = $path;

                $url = Storage::disk($disk)->url($path);
                if (preg_match(ThemePreset::HERO_ASSET_PATTERN, $url) !== 1) {
                    throw new \RuntimeException("hero_assets.{$surface} would resolve to a URL that can never render: {$url}");
                }

                $result[$surface] = $url;
            }
        } finally {
            $zip->close();
        }

        return $result;
    }

    /** @param  list<string>  $paths */
    private function cleanupAssets(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        Storage::disk(MediaStorage::disk())->delete($paths);
    }
}
