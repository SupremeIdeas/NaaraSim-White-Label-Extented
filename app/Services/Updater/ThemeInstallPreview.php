<?php

namespace App\Services\Updater;

/**
 * The result of previewing a theme `.naaraupdate` package (Batch 3 §3) — verified
 * and shape-validated, but nothing written to the database or storage yet. Feeds
 * the admin confirmation screen: name, persona, a live colour-swatch preview,
 * and any font allow-list warnings, all BEFORE the install button is enabled.
 */
class ThemeInstallPreview
{
    /**
     * @param  array<string,string>  $colorSwatches  key => "R G B" triple, ONLY entries that already
     *                                                pass ThemePreset::isValidColorTriple() — never raw,
     *                                                unvalidated upload data (this feeds an inline `style`
     *                                                attribute, so anything unvalidated here is an
     *                                                injection risk, not just a cosmetic glitch).
     * @param  array<string,mixed>  $iconFamily
     * @param  array<string,string>  $layoutVariants
     * @param  list<string>  $fontWarnings  human-readable "font X isn't approved yet" messages
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $persona,
        public readonly array $colorSwatches,
        public readonly array $iconFamily,
        public readonly array $layoutVariants,
        public readonly array $fontWarnings,
        public readonly string $packageId,
        public readonly string $version,
    ) {
    }
}
