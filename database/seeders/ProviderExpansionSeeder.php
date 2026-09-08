<?php

namespace Database\Seeders;

use App\Models\ProviderRegistry;
use Illuminate\Database\Seeder;

/**
 * NAARA-BUILD-18 — registers the newly-built adapters into provider_registry so
 * they surface in the Operations Center (BUILD-17). Every row ships
 * enabled=false: the adapters are built and tested, but Frank turns each on
 * deliberately once real credentials exist. These providers are NOT in the
 * ProviderModels lanes (see PLATFORM-STATE flag), so stack + product_families are
 * set explicitly here rather than derived.
 *
 * URL discipline (blueprint §3.4/§4): only URLs confirmed with reasonable
 * confidence are seeded; the rest are left null for the admin, flagged in
 * docs/PLATFORM-STATE.md — a wrong link is worse than a blank one.
 */
class ProviderExpansionSeeder extends Seeder
{
    /** key => [stack, [families], tier, login|null, docs|null]. */
    private const PROVIDERS = [
        // Immediate tier — self-service.
        'smspool' => ['sms', ['naara_verify', 'naara_rent'], 'self_service', 'https://www.smspool.net/dashboard', 'https://www.smspool.net/documentation'],
        'onlinesim' => ['sms', ['naara_verify', 'naara_rent'], 'self_service', 'https://onlinesim.io/', 'https://onlinesim.io/docs'],
        'plivo' => ['permanent', ['naara_line'], 'self_service', 'https://console.plivo.com/', 'https://www.plivo.com/docs/'],
        'bitrefill' => ['gift', ['naaragift'], 'self_service', 'https://www.bitrefill.com/', 'https://developers.bitrefill.com/'],
        'esimaccess' => ['esim', ['naara_data'], 'self_service', null, 'https://docs.esimaccess.com/'],
        // Placeholder tier — built, enabled later (NDA / enterprise-gated).
        'tillo' => ['gift', ['naaragift'], 'enterprise', null, 'https://tillo.readme.io/'],
        'ubigi' => ['esim', ['naara_data'], 'enterprise', null, null],
        'sonetel' => ['permanent', ['naara_line'], 'small_business', 'https://sonetel.com/en/login/', 'https://docs.sonetel.com/'],
    ];

    public function run(): void
    {
        foreach (self::PROVIDERS as $key => [$stack, $families, $tier, $login, $docs]) {
            ProviderRegistry::updateOrCreate(
                ['provider_key' => $key],
                [
                    'stack' => $stack,
                    'product_families' => $families,
                    'onboarding_tier' => $tier,
                    'dashboard_login_url' => $login,
                    'docs_url' => $docs,
                    'enabled' => false,          // Frank enables deliberately (§1)
                    'status' => 'coming_soon',
                ],
            );
        }

        ProviderRegistry::flushSnapshot();
    }
}
