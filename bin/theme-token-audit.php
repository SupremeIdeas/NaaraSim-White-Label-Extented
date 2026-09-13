<?php

/**
 * NaaraSim — theme-token integrity audit.
 *
 * Static scanner + report ONLY — it never rewrites a file. Two confirmed root
 * causes explain "changing the theme doesn't change everything it should":
 * (1) dark mode used to collapse to one shared palette (fixed 2026-09-13, see
 * ThemePreset::darkOverrideTokens()); (2) several Blade files bypass the
 * token system entirely with LITERAL colors — a hex code, or a Tailwind
 * default-palette utility class — that no theme, light or dark, can ever
 * change. This tool finds every remaining instance of (2).
 *
 * It deliberately does NOT auto-rewrite anything: some literal colors are
 * legitimate (status colors — success/error/warning — are conventionally
 * NOT theme-branded in most design systems, confirmed against this
 * codebase's own usage: red/rose/green/emerald/amber/yellow overwhelmingly
 * mark error/success/warning states here, not brand chrome). Remediation is
 * a second, human-reviewed pass, file by file, replacing each flagged
 * non-semantic match with the token-linked class it should have been
 * (bg-primary, text-accent, dark:bg-navy, etc.) — never a blind regex
 * find/replace that could reclassify a real status color as a bug.
 *
 * Usage:  php bin/theme-token-audit.php [--strict] [--include-admin]
 *   --strict          exit(1) if anything is flagged (for a future CI gate —
 *                     not wired into a workflow right now, see CLAUDE.md
 *                     "GITHUB ACTIONS BUDGET"; run it locally, or add it back
 *                     to tests.yml once the Actions budget resets and this
 *                     audit is believed clean).
 *   --include-admin   also scan resources/views/livewire/admin/**. Excluded
 *                     by default — confirmed by checking every App\Livewire\
 *                     Admin\* component's #[Layout(...)] attribute, the admin
 *                     panel (blueprint Section 25, `/adminmaster`) is a
 *                     categorically separate surface: every one of them
 *                     renders through components.layouts.admin (or the admin
 *                     login's components.layouts.auth), NEVER
 *                     components.layouts.customer — ThemePreset::styleCss()
 *                     is never invoked there at all. Its literal navy/slate
 *                     palette is that surface's own deliberate, fixed design,
 *                     not a bug this audit should chase.
 */
$root = dirname(__DIR__);
$strict = in_array('--strict', $argv, true);
$includeAdmin = in_array('--include-admin', $argv, true);

// Tailwind's own default-palette family names (v3). NaaraSim's tailwind.config.js
// only EXTENDS the theme (adds primary/accent/navy/action/success/warning/danger)
// — it never removes Tailwind's defaults, so every one of these families is still
// a valid, working utility class anywhere in the app. That's exactly the bug:
// nothing stops a Blade file from reaching for `text-slate-400` instead of the
// theme-aware `text-slate` … er, `text-primary`/`dark:text-slate-*` equivalent.
$defaultPalette = [
    'slate', 'gray', 'zinc', 'neutral', 'stone',
    'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
    'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
];

// Confirmed by grepping actual usage across resources/views (see the PR this
// script shipped in for the raw counts): red/rose, green/emerald, and
// amber/yellow are overwhelmingly used here for error/success/warning states,
// matching the doc's own guidance that status colors are conventionally
// exempt from theme branding. Everything else in $defaultPalette — slate
// most of all (8,700+ raw hits) — is either neutral chrome that should track
// the theme, or a one-off literal accent standing in for brand primary/accent.
$semanticAllowList = ['red', 'rose', 'green', 'emerald', 'amber', 'yellow'];

$utilityPrefixes = ['bg', 'text', 'border', 'ring', 'from', 'via', 'to', 'divide', 'decoration', 'outline', 'shadow', 'placeholder', 'caret', 'accent', 'fill', 'stroke'];
$flaggedFamilies = implode('|', array_diff($defaultPalette, $semanticAllowList));
$utilityPattern = '/\\b('.implode('|', $utilityPrefixes).')-('.$flaggedFamilies.')-(\\d{2,3})\\b/';

// Hex codes are only a bug in CHROME (backgrounds, text, borders) — not in
// decorative SVG artwork, where a literal fill/stroke color is normal and
// often intentional (a brand mark, a themed illustration). Skip any line that
// is clearly inside an SVG element; the icon sprite file is 100% SVG defs, so
// skip it outright.
$hexPattern = '/#[0-9a-fA-F]{3,8}\b/';
$svgLineMarkers = ['<svg', '<path', '<stop', '<linearGradient', '<symbol', '<circle', '<rect', '<polygon', '<line ', '<use '];

/** @var array<string, array{hex: int, utility: int, matches: array<int, array{line:int, category:string, match:string}>}> */
$report = [];

$views = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/views', FilesystemIterator::SKIP_DOTS)
);

foreach ($views as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
    if ($relative === 'resources/views/partials/icon-sprite.blade.php') {
        continue; // 100% SVG defs — every hex/utility-shaped token here is decorative icon artwork.
    }
    if (! $includeAdmin && str_starts_with($relative, 'resources/views/livewire/admin/')) {
        continue; // Admin's own fixed design system — see --include-admin above.
    }

    $lines = file($file->getPathname());
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $i => $line) {
        $lineNo = $i + 1;
        $isSvgLine = false;
        foreach ($svgLineMarkers as $marker) {
            if (str_contains($line, $marker)) {
                $isSvgLine = true;
                break;
            }
        }

        if (! $isSvgLine && preg_match_all($hexPattern, $line, $hexMatches)) {
            foreach ($hexMatches[0] as $match) {
                $report[$relative]['matches'][] = ['line' => $lineNo, 'category' => 'hex', 'match' => $match];
                $report[$relative]['hex'] = ($report[$relative]['hex'] ?? 0) + 1;
            }
        }

        if (preg_match_all($utilityPattern, $line, $utilMatches)) {
            foreach ($utilMatches[0] as $match) {
                $report[$relative]['matches'][] = ['line' => $lineNo, 'category' => 'utility', 'match' => $match];
                $report[$relative]['utility'] = ($report[$relative]['utility'] ?? 0) + 1;
            }
        }
    }
}

uasort($report, function ($a, $b) {
    $totalA = ($a['hex'] ?? 0) + ($a['utility'] ?? 0);
    $totalB = ($b['hex'] ?? 0) + ($b['utility'] ?? 0);

    return $totalB <=> $totalA;
});

$grandTotal = 0;
foreach ($report as $file => $data) {
    $hexCount = $data['hex'] ?? 0;
    $utilityCount = $data['utility'] ?? 0;
    $total = $hexCount + $utilityCount;
    $grandTotal += $total;
    echo "\n{$file}  ({$total} — {$hexCount} hex, {$utilityCount} utility)\n";
    foreach ($data['matches'] as $m) {
        echo "  L{$m['line']}  [{$m['category']}]  {$m['match']}\n";
    }
}

echo "\n".str_repeat('-', 60)."\n";
echo "Files flagged: ".count($report)."   Total matches: {$grandTotal}\n";
echo "Semantic allow-list (never flagged): ".implode(', ', $semanticAllowList)."\n";

if ($strict && $grandTotal > 0) {
    exit(1);
}
exit(0);
