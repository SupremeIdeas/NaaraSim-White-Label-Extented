<?php

namespace App\Support\Appearance;

/**
 * Turns ANY colour a member picks into a readable accent (Prompt 20 §38). Server-side and never trusted from the client.
 * A line-for-line port of derive() in docs/appearance/wireframes/naara-dashboard-system-v9-surface-core13.html: the
 * colour is never rejected, it is darkened/lightened in 6% steps toward #02121F (or white) until
 *   - white text on cta_b >= 4.5:1, white on cta_a >= 3:1, and teal_ink >= 4.5:1 on the mode's surface.
 */
class AccentDeriver
{
    private const WHITE = [255, 255, 255];

    private const INK = [2, 18, 31];

    /** Surface the ink must read on: dark `8 32 47`, light white. */
    private const SURFACE_DARK = [8, 32, 47];

    /** @return array{teal: string, cta_a: string, cta_b: string, teal_ink: string, acc_a: string, acc_b: string, acc_c: string, adjusted: bool} */
    public static function derive(string $hex, string $mode): array
    {
        $base = self::rgb($hex);

        $ctaB = $base;
        for ($n = 0; self::contrast($ctaB, self::WHITE) < 4.5 && $n++ < 40;) {
            $ctaB = self::mix($ctaB, self::INK, .06);
        }
        $ctaA = $base;
        for ($n = 0; self::contrast($ctaA, self::WHITE) < 3 && $n++ < 40;) {
            $ctaA = self::mix($ctaA, self::INK, .06);
        }
        $surface = $mode === 'light' ? self::WHITE : self::SURFACE_DARK;
        $ink = $base;
        for ($n = 0; self::contrast($ink, $surface) < 4.5 && $n++ < 40;) {
            $ink = self::mix($ink, $mode === 'light' ? self::INK : self::WHITE, .06);
        }

        return [
            'teal' => self::triple($base), 'cta_a' => self::triple($ctaA), 'cta_b' => self::triple($ctaB), 'teal_ink' => self::triple($ink),
            'acc_a' => self::triple($ctaA), 'acc_b' => self::triple($ctaB), 'acc_c' => self::triple(self::mix($ctaB, self::INK, .45)),
            'adjusted' => $ctaA !== $base || $ink !== $base,
        ];
    }

    public static function isValidHex(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-f]{6}$/', $hex) === 1;
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /** @param array{int,int,int} $c */
    public static function luminance(array $c): float
    {
        $f = static function (int $v): float {
            $v /= 255;

            return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
        };

        return .2126 * $f($c[0]) + .7152 * $f($c[1]) + .0722 * $f($c[2]);
    }

    /** WCAG contrast ratio between two colours. @param array{int,int,int} $a @param array{int,int,int} $b */
    public static function contrast(array $a, array $b): float
    {
        $x = self::luminance($a);
        $y = self::luminance($b);

        return (max($x, $y) + .05) / (min($x, $y) + .05);
    }

    /** @param array{int,int,int} $a @param array{int,int,int} $b @return array{int,int,int} */
    private static function mix(array $a, array $b, float $t): array
    {
        // JS Math.round: half rounds up (all values here are >= 0, so PHP's half-away-from-zero is identical).
        return [(int) round($a[0] + ($b[0] - $a[0]) * $t), (int) round($a[1] + ($b[1] - $a[1]) * $t), (int) round($a[2] + ($b[2] - $a[2]) * $t)];
    }

    /** @param array{int,int,int} $c */
    private static function triple(array $c): string
    {
        return implode(' ', $c);
    }
}
