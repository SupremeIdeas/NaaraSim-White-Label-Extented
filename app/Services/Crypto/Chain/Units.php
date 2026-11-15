<?php

namespace App\Services\Crypto\Chain;

/**
 * Exact money math for chain amounts, in pure PHP decimal strings: no float, no int overflow, no extension (bcmath and gmp are often missing on shared hosting).
 * A uint256 does not fit a 64-bit integer, so every raw amount is a string of digits. Conversion to dollars ALWAYS rounds DOWN (blueprint invariant I6): we may
 * credit a member a hair less than they sent, never a hair more.
 */
final class Units
{
    public static function isUint(string $v): bool
    {
        return $v !== '' && strlen($v) <= 78 && ctype_digit($v) && ($v === '0' || $v[0] !== '0');
    }

    public static function isAddress(string $v): bool
    {
        return preg_match('/^0x[0-9a-fA-F]{40}$/', $v) === 1;
    }

    public static function isHash(string $v): bool
    {
        return preg_match('/^0x[0-9a-fA-F]{64}$/', $v) === 1;
    }

    /** Hex quantity ("0x1a2b", any length) to a decimal digit string. */
    public static function hexToDec(string $hex): string
    {
        if (preg_match('/^0x[0-9a-fA-F]*$/', $hex) !== 1) {
            throw new \InvalidArgumentException('Not a hex quantity.');
        }
        $digits = ltrim(substr($hex, 2), '0');
        if ($digits === '') {
            return '0';
        }
        $dec = [0];   // little-endian base-10 digits
        foreach (str_split(strtolower($digits)) as $h) {
            $carry = hexdec($h);
            foreach ($dec as $i => $d) {
                $v = $d * 16 + $carry;
                $dec[$i] = $v % 10;
                $carry = intdiv($v, 10);
            }
            while ($carry > 0) {
                $dec[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }

        return implode('', array_reverse($dec));
    }

    /** Multiply a digit string by a small non-negative integer. */
    public static function mulSmall(string $digits, int $factor): string
    {
        if ($factor < 0 || ! self::isUint($digits)) {
            throw new \InvalidArgumentException('Unsigned digits only.');
        }
        if ($factor === 0 || $digits === '0') {
            return '0';
        }
        $out = '';
        $carry = 0;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $v = ((int) $digits[$i]) * $factor + $carry;
            $out = ($v % 10).$out;
            $carry = intdiv($v, 10);
        }
        while ($carry > 0) {
            $out = ($carry % 10).$out;
            $carry = intdiv($carry, 10);
        }

        return $out;
    }

    /** The exact decimal value of a raw amount, trailing zeros trimmed ("12500000", 6 gives "12.5"). */
    public static function rawToDecimal(string $raw, int $decimals): string
    {
        if (! self::isUint($raw) || $decimals < 0 || $decimals > 36) {
            throw new \InvalidArgumentException('Bad raw amount or decimals.');
        }
        if ($decimals === 0) {
            return $raw;
        }
        $padded = str_pad($raw, $decimals + 1, '0', STR_PAD_LEFT);
        $int = substr($padded, 0, -$decimals);
        $frac = rtrim(substr($padded, -$decimals), '0');

        return $frac === '' ? $int : $int.'.'.$frac;
    }

    /**
     * Dollars credited for a raw token amount: raw / 10^decimals x peg, TRUNCATED to $scale places. `peg` is a 4-decimal multiplier ("1.0000", "0.9990"; "0" pauses).
     *
     * @return string fixed-point string with exactly $scale decimals, e.g. "12.5000"
     */
    public static function rawToUsd(string $raw, int $decimals, string $peg = '1.0000', int $scale = 4): string
    {
        if (! self::isUint($raw) || $decimals < 0 || $decimals > 36 || $scale < 0 || $scale > 8 || preg_match('/^\d{1,3}(\.\d{1,4})?$/', $peg) !== 1) {
            throw new \InvalidArgumentException('Bad amount, decimals, peg or scale.');
        }
        [$pi, $pf] = array_pad(explode('.', $peg, 2), 2, '');
        $pegInt = (int) ($pi.str_pad($pf, 4, '0'));          // peg x 10^4
        $product = self::mulSmall($raw, $pegInt);              // raw x peg x 10^4  ==  usd x 10^(decimals+4)
        $shift = $decimals + 4 - $scale;                       // keep $scale decimals: drop the last $shift digits (rounding down)
        $scaled = $shift > 0 ? (strlen($product) > $shift ? substr($product, 0, -$shift) : '0') : $product.str_repeat('0', -$shift);
        $scaled = ltrim($scaled, '0') === '' ? '0' : ltrim($scaled, '0');
        if ($scale === 0) {
            return $scaled;
        }
        $padded = str_pad($scaled, $scale + 1, '0', STR_PAD_LEFT);

        return substr($padded, 0, -$scale).'.'.substr($padded, -$scale);
    }

    /** What was dropped by the round-down when the peg is exactly 1: the remainder in raw units (never credited, only recorded). */
    public static function dustRaw(string $raw, int $decimals, int $scale = 4): string
    {
        if (! self::isUint($raw) || $decimals <= $scale) {
            return '0';
        }
        $keep = $decimals - $scale;                            // digits below the credited precision
        $dust = ltrim(substr($raw, -$keep), '0');

        return $dust === '' ? '0' : $dust;
    }
}
