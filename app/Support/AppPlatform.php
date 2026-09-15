<?php

namespace App\Support;

/**
 * Native-app platform detection (App Store payments-compliance doc, BUILD-5 §6).
 * `AppStudio::medianConfig()` always sets `general.userAgentAdd` to the fixed
 * literal `'median'` for EVERY native build regardless of branding — that
 * token, not the (brand-name-based, identical-on-both-platforms) per-OS
 * additions, is what reliably marks "this request came from our native
 * wrapper app" at all. Combined with the OS's own standard UA substrings
 * (present on every real device regardless of app config) this tells us which
 * wrapper platform is asking, with no extra native code and no new build
 * config to keep in sync.
 *
 * Used to gate the iOS-only payment-structure carve-outs
 * docs/APP-STORE-PAYMENTS-COMPLIANCE.md requires: wallet top-up entry point,
 * Wizard fee, merchant-upgrade fee, and Naara Gift must never silently charge
 * via our own gateway inside a native iOS build.
 */
class AppPlatform
{
    /** True if this request came from either native wrapper (Android or iOS). */
    public static function isNativeApp(?string $userAgent = null): bool
    {
        $ua = $userAgent ?? (string) (request()->userAgent() ?? '');

        return str_contains($ua, 'median');
    }

    /** True if this request came from the native iOS wrapper specifically. */
    public static function isIosBuild(?string $userAgent = null): bool
    {
        $ua = $userAgent ?? (string) (request()->userAgent() ?? '');

        return self::isNativeApp($ua) && (bool) preg_match('/iPhone|iPad|iPod/i', $ua);
    }

    /** True if this request came from the native Android wrapper specifically. */
    public static function isAndroidBuild(?string $userAgent = null): bool
    {
        $ua = $userAgent ?? (string) (request()->userAgent() ?? '');

        return self::isNativeApp($ua) && str_contains($ua, 'Android') && ! self::isIosBuild($ua);
    }
}
