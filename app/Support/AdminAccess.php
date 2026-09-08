<?php

namespace App\Support;

use App\Support\Geo\GeoResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Panel-managed admin access rules (owner request — fix_admin.md Part 3). Both
 * layers are OFF by default and admin-controlled, so the admin can sign in from
 * any device or country until they deliberately restrict it.
 *
 *   - IP allow-list: only listed IPs may reach the panel.
 *   - Country allow-list: only listed countries may reach the panel.
 *
 * FAIL-OPEN on unknowns: if the country can't be resolved (no Cloudflare header /
 * no geo backend), access is ALLOWED and logged — a geo hiccup must never lock an
 * admin out. All blocks are logged (like the env IP list) so a locked-out
 * operator can diagnose them instead of chasing a silent 404.
 */
class AdminAccess
{
    /**
     * Returns a short block reason ('ip' | 'country') if the request should be
     * denied by the panel-managed rules, or null if allowed.
     */
    public static function blockReason(Request $request): ?string
    {
        // Panel IP allow-list (in addition to the env one in config/admin.php).
        if (SecuritySettings::adminIpAllowlistEnabled()) {
            $ips = SecuritySettings::adminIpAllowlist();
            if (! empty($ips) && ! in_array($request->ip(), $ips, true)) {
                Log::warning('[admin] Blocked by panel IP allow-list.', ['ip' => $request->ip()]);

                return 'ip';
            }
        }

        // Country allow-list.
        if (SecuritySettings::adminCountryAllowlistEnabled()) {
            $countries = SecuritySettings::adminAllowedCountries();
            if (! empty($countries)) {
                $country = app(GeoResolver::class)->country($request);

                if ($country === null) {
                    // Fail open — never lock out when geo can't be resolved.
                    Log::warning('[admin] Country allow-list is on but the request country could not be resolved — allowing (fail-open). Wire a GeoResolver or Cloudflare to enforce.', [
                        'ip' => $request->ip(),
                    ]);
                } elseif (! in_array($country, $countries, true)) {
                    Log::warning('[admin] Blocked by country allow-list.', [
                        'ip' => $request->ip(),
                        'country' => $country,
                        'allowed' => $countries,
                    ]);

                    return 'country';
                }
            }
        }

        return null;
    }

    /** The current request's resolved country (for the self-lockout warning). */
    public static function currentCountry(Request $request): ?string
    {
        return app(GeoResolver::class)->country($request);
    }
}
