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
}
