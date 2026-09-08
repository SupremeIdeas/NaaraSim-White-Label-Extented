<?php

namespace App\Support\Geo;

use Illuminate\Http\Request;

/**
 * Default GeoResolver: reads Cloudflare's CF-IPCountry header. Zero dependency —
 * no GeoLite2 DB, no paid API — and accurate for any site fronted by Cloudflare
 * (a very common self-hosted setup). Returns null when the header is absent or
 * Cloudflare couldn't determine the country ("XX"/"T1"), so callers fail open.
 */
class CloudflareGeoResolver implements GeoResolver
{
    public function country(Request $request): ?string
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry')));

        if ($code === '' || in_array($code, ['XX', 'T1'], true) || strlen($code) !== 2) {
            return null;
        }

        return $code;
    }
}
