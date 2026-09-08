<?php

namespace App\Support;

/**
 * Supplier-identity scrub (money-safety rule 1.2 / blueprint §6). NaaraSim never
 * reveals which third party supplies a plan. Provider catalogue titles sometimes
 * embed the supplier's own brand ("Airalo 1GB", "eSIM Go Europe"), so every plan
 * name is passed through this before it is stored/displayed — a defensive net so
 * a supplier brand can never leak through accidentally, whatever a provider names
 * its bundles.
 *
 * Note: the standalone word "eSIM" is legitimate and kept; only brand tokens
 * (multi-word/whole-word supplier names) are removed.
 */
class SupplierScrub
{
    /** Supplier brand tokens (case-insensitive, whole-word), longest first. */
    private const BRANDS = [
        'esim go', 'esim-go', 'esimgo',
        'airalo',
        'quibity', 'esim.sm',
        'zendit',
        'one global', '1global', '1 global', 'oneglobal',
        'monty mobile', 'montymobile',
        'gigs',
    ];

    public static function name(string $name): string
    {
        $clean = $name;

        foreach (self::BRANDS as $brand) {
            $pattern = '/\b'.preg_quote($brand, '/').'\b/i';
            $clean = preg_replace($pattern, ' ', $clean) ?? $clean;
        }

        // Tidy up: collapse whitespace and any separator now left dangling
        // (leading/trailing/doubled "—", "-", "|", ":").
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s*([—\-|:])\s*\1*/', ' $1 ', $clean) ?? $clean;
        $clean = trim($clean, " \t—-|:");
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        // If scrubbing emptied the name, fall back to a neutral label.
        return trim($clean) !== '' ? trim($clean) : 'eSIM plan';
    }
}
