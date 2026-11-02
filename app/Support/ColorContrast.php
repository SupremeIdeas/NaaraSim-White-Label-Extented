<?php

namespace App\Support;

/**
 * WCAG 2.x contrast-ratio math — a PHP port of the color-system skill's
 * `contrast_check.py` (root/.claude/skills/color-system), used so theme
 * presets can be verified in an automated test rather than eyeballed. Keep
 * this in lockstep with that script's math if either ever changes; the
 * skill is the source of truth for the THRESHOLDS (3:1 UI-component, 4.5:1
 * normal text), this class just makes them checkable in PHP/CI.
 */
class ColorContrast
{
    /** Accepts a channel-triple ("R G B") or a "#RRGGBB"/"RRGGBB" hex string. */
    public static function ratio(string $a, string $b): float
    {
        return self::contrastFromLuminance(
            self::relativeLuminance(self::toRgb($a)),
            self::relativeLuminance(self::toRgb($b)),
        );
    }

    /** @return array{0:int,1:int,2:int} */
    private static function toRgb(string $color): array
    {
        $color = trim($color);
        if (str_contains($color, ' ')) {
            [$r, $g, $b] = array_map('intval', preg_split('/\s+/', $color));

            return [$r, $g, $b];
        }

        $hex = ltrim($color, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function relativeLuminance(array $rgb): float
    {
        $channel = function (int $c): float {
            $c = $c / 255.0;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        [$r, $g, $b] = array_map($channel, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function contrastFromLuminance(float $a, float $b): float
    {
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Mix a colour toward black in 4% steps until it reaches `$min` contrast against `$against`; the colour itself if it already does.
     * Used so a button colour an admin picks (a bright coral, a light cyan) can still carry white text. Returns an "R G B" triple.
     */
    public static function darkenToContrast(string $color, string $against = '#FFFFFF', float $min = 3.0): string
    {
        return self::shiftToContrast($color, $against, $min, 0);
    }

    /** Mix a colour toward white until it reaches `$min` contrast against `$against` (a dark surface). Returns an "R G B" triple. */
    public static function lightenToContrast(string $color, string $against, float $min = 4.5): string
    {
        return self::shiftToContrast($color, $against, $min, 255);
    }

    private static function shiftToContrast(string $color, string $against, float $min, int $target): string
    {
        $rgb = self::toRgb($color);
        for ($i = 0; $i <= 25; $i++) {
            $k = $i * 0.04;
            $try = array_map(fn ($c) => (int) round($c + ($target - $c) * $k), $rgb);
            if (self::ratio(implode(' ', $try), $against) >= $min) {
                return implode(' ', $try);
            }
        }

        return implode(' ', array_fill(0, 3, $target));
    }

    /** "R G B" => "#RRGGBB". */
    public static function toHex(string $triple): string
    {
        return sprintf('#%02X%02X%02X', ...self::toRgb($triple));
    }
}
