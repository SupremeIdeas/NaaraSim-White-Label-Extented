<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Marketing/analytics tracking (owner request). Admin saves a Facebook Pixel ID
 * and/or a Google (GA4) Measurement ID; the snippets are injected into the page
 * head ONLY when an ID is present. IDs are validated to a safe shape so nothing
 * arbitrary is ever echoed into the page.
 */
class Tracking
{
    public const PIXEL_KEY = 'tracking.fb_pixel_id';

    public const GA_KEY = 'tracking.ga_id';

    public static function pixelId(): ?string
    {
        $id = trim((string) self::read(self::PIXEL_KEY));

        // Facebook Pixel IDs are numeric (typically 15–16 digits).
        return preg_match('/^\d{6,20}$/', $id) ? $id : null;
    }

    public static function gaId(): ?string
    {
        $id = trim((string) self::read(self::GA_KEY));

        // GA4 measurement IDs look like G-XXXXXXXXXX.
        return preg_match('/^G-[A-Z0-9]{6,20}$/i', $id) ? strtoupper($id) : null;
    }

    /** Read a setting, degrading to '' pre-install / on any DB hiccup. */
    private static function read(string $key): string
    {
        try {
            return (string) Setting::getValue($key, '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function enabled(): bool
    {
        return self::pixelId() !== null || self::gaId() !== null;
    }

    public static function save(string $pixel, string $ga): void
    {
        Setting::setValue(self::PIXEL_KEY, trim($pixel), 'tracking');
        Setting::setValue(self::GA_KEY, trim($ga), 'tracking');
    }
}
