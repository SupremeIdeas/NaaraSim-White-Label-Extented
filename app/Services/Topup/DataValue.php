<?php

namespace App\Services\Topup;

/** Reads a supplier's bundle label ("3GB, 30 Days", "Unlimited, 1 Day") for top-up data bundles. */
final class DataValue
{
    /** @return array{0: ?int, 1: ?int, 2: bool} data in MB (null when unlimited or unknown), days, unlimited */
    public static function parse(string $v): array
    {
        $unlimited = (bool) preg_match('/unlimited/i', $v);
        $mb = null;
        if (preg_match('/([\d.]+)\s*(GB|MB|TB)/i', $v, $m)) {
            $n = (float) $m[1];
            $mb = (int) round(match (strtoupper($m[2])) { 'TB' => $n * 1024 * 1024, 'GB' => $n * 1024, default => $n });
        }
        $days = null;
        if (preg_match('/(\d+)\s*(day|week|month)/i', $v, $m)) {
            $days = (int) $m[1] * match (strtolower($m[2])) { 'week' => 7, 'month' => 30, default => 1 };
        }

        return [$unlimited ? null : $mb, $days, $unlimited];
    }
}
