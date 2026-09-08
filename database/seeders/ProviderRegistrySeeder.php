<?php

namespace Database\Seeders;

use App\Models\ProviderRegistry;
use Illuminate\Database\Seeder;

/**
 * NAARA-BUILD-14 §3.4 — seeds the admin-facing metadata for every already-
 * integrated provider (onboarding tier + the practical dashboard/docs links an
 * admin clicks through to when grabbing or rotating keys). Live-truth fields are
 * left for ProviderHealth to fill on its next run.
 *
 * URL discipline: only URLs confirmed with high confidence are seeded. Where a
 * provider's current portal URL can't be confirmed here, it's left null for the
 * admin to fill in — a wrong link is worse than a blank one (blueprint §3.4).
 * The nulls are listed in docs/PLATFORM-STATE.md.
 *
 * onboarding_tier is best-effort admin metadata (how much friction each vendor
 * took to onboard) and is freely editable later; it only drives the Ops Center
 * sort order in BUILD-17.
 */
class ProviderRegistrySeeder extends Seeder
{
    /** provider_key => [onboarding_tier, dashboard_login_url|null, docs_url|null]. */
    private const META = [
        // eSIM stack
        'esimgo' => ['enterprise', 'https://portal.esim-go.com/', 'https://docs.esim-go.com/'],
        'airalo' => ['enterprise', 'https://partners.airalo.com/', 'https://partners-doc.airalo.com/'],
        'quibity' => ['small_business', null, null],
        'zendit' => ['self_service', null, null],
        'oneglobal' => ['enterprise', null, null],
        'montymobile' => ['enterprise', null, null],
        'gigs' => ['small_business', null, null],
        // SMS / OTP / rental stack
        'getatext' => ['self_service', null, null],
        'fivesim' => ['self_service', 'https://5sim.net/', null],
        'herosms' => ['self_service', null, null],
        'virtsms' => ['self_service', null, null],
        // Permanent / voice stack
        'twilio' => ['individual_kyc', 'https://console.twilio.com/', 'https://www.twilio.com/docs'],
        'telnyx' => ['individual_kyc', 'https://portal.telnyx.com/', 'https://developers.telnyx.com/'],
    ];

    public function run(): void
    {
        foreach (self::META as $key => [$tier, $loginUrl, $docsUrl]) {
            $meta = ProviderRegistry::deriveMeta($key);

            ProviderRegistry::updateOrCreate(
                ['provider_key' => $key],
                [
                    'stack' => $meta['stack'],
                    'product_families' => $meta['product_families'],
                    'onboarding_tier' => $tier,
                    'dashboard_login_url' => $loginUrl,
                    'docs_url' => $docsUrl,
                ],
            );
        }

        ProviderRegistry::flushSnapshot();
    }
}
