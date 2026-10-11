<?php

namespace App\Support;

/**
 * Exact money arithmetic for the Funding Radar without floats or bcmath: amounts are
 * carried as integer "units" of 0.0001 and only formatted at the edge.
 */
final class Money4
{
    public const SCALE = 10000;

    public static function units(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * self::SCALE);
    }

    public static function str(int $units): string
    {
        $sign = $units < 0 ? '-' : '';
        $abs = abs($units);

        return $sign.intdiv($abs, self::SCALE).'.'.str_pad((string) ($abs % self::SCALE), 4, '0', STR_PAD_LEFT);
    }

    /** Float for display/charts only — never feed this back into arithmetic. */
    public static function float(int $units): float
    {
        return $units / self::SCALE;
    }
}
