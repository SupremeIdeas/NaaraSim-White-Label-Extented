<?php

namespace App\Support\Geo;

use Illuminate\Http\Request;

/**
 * Resolves a request's country (ISO-3166 alpha-2, uppercase) or null if unknown.
 * Pluggable so a deployer can wire in a GeoLite2 DB or a geo-IP service; the
 * shipped default reads Cloudflare's CF-IPCountry header (zero dependency, works
 * for any Cloudflare-fronted site). When a country can't be resolved, callers
 * must FAIL OPEN — never lock an admin out because geo lookup was unavailable.
 */
interface GeoResolver
{
    public function country(Request $request): ?string;
}
