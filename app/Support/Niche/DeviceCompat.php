<?php

namespace App\Support\Niche;

/**
 * eSIM device-compatibility check (blueprint Section 32). Run BEFORE an eSIM
 * purchase so a user on a non-eSIM phone can't buy something they can't use.
 * Returns true (known supported), false (known unsupported), or null (unknown —
 * guide the user to check *#06# / their device settings).
 */
class DeviceCompat
{
    /** Substrings of well-known eSIM-capable devices (non-exhaustive). */
    private const SUPPORTED = [
        // Apple
        'iphone 11', 'iphone 12', 'iphone 13', 'iphone 14', 'iphone 15', 'iphone 16',
        'iphone xr', 'iphone xs', 'iphone se 2', 'iphone se 3', 'ipad pro', 'ipad air',
        // Google
        'pixel 3', 'pixel 4', 'pixel 5', 'pixel 6', 'pixel 7', 'pixel 8', 'pixel 9',
        // Samsung
        'galaxy s20', 'galaxy s21', 'galaxy s22', 'galaxy s23', 'galaxy s24', 'galaxy s25',
        'galaxy z fold', 'galaxy z flip', 'galaxy note 20',
        // Others
        'huawei p40', 'huawei mate 40', 'motorola razr', 'oppo find x', 'rakuten',
    ];

    /** Devices explicitly known NOT to support eSIM (common questions). */
    private const UNSUPPORTED = [
        'iphone 6', 'iphone 7', 'iphone 8', 'iphone x ', 'iphone se 1',
        'galaxy s10', 'galaxy s9', 'redmi', 'tecno spark', 'infinix hot',
    ];

    public static function check(string $device): ?bool
    {
        $q = mb_strtolower(trim($device));
        if ($q === '') {
            return null;
        }

        foreach (self::UNSUPPORTED as $needle) {
            if (str_contains($q, $needle)) {
                return false;
            }
        }
        foreach (self::SUPPORTED as $needle) {
            if (str_contains($q, $needle)) {
                return true;
            }
        }

        return null;
    }

    /** Human guidance for the "unknown" path. */
    public static function howToCheck(): string
    {
        return 'Dial *#06# on your phone — if you see an "EID" number, your device supports eSIM. '
            .'On iPhone: Settings → General → About → look for an available EID. '
            .'On Android: Settings → About phone / Connections → SIM manager.';
    }
}
