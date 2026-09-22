<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Architecture-fitness guard — surgery Phase 6. THIS IS A WHITE-LABEL BUILD and
 * must remain a consumer + a leaf puller forever: never an issuer, an oversight
 * registry, a reseller of licenses, or an update distributor. If any of that
 * capability reappears here (e.g. a folder copied from master again, or a well-
 * meaning re-add), THIS TEST FAILS THE BUILD. It is the guard that makes the
 * license-boundary surgery permanent rather than a fix that quietly regresses.
 * See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md.
 */
class WhiteLabelBoundaryFitnessTest extends TestCase
{
    public function test_no_issuer_oversight_or_distributor_class_exists_on_this_fork(): void
    {
        $forbidden = [
            // Issuer
            'App\\Services\\Updater\\WhiteLabelLicenseService',
            'App\\Http\\Controllers\\Api\\V1\\WhiteLabel\\WhiteLabelLicenseController',
            // Oversight
            'App\\Livewire\\Admin\\WhiteLabelRegistry',
            'App\\Livewire\\MerchantWhiteLabel',
            'App\\Http\\Controllers\\Admin\\WhiteLabelIntakePdfController',
            'App\\Services\\Updater\\WhiteLabelProjectIntakeService',
            'App\\Models\\WhiteLabelInstance',
            'App\\Models\\WhiteLabelLicensePlan',
            'App\\Models\\WhiteLabelLicensePayment',
            'App\\Models\\WhiteLabelApiLog',
            'App\\Models\\WhiteLabelGuideLink',
            // Distributor
            'App\\Http\\Controllers\\Api\\V1\\WhiteLabel\\WhiteLabelUpdateController',
            'App\\Http\\Controllers\\Api\\V1\\WhiteLabel\\WhiteLabelThemeController',
            'App\\Services\\Updater\\PackagePublisher',
            'App\\Services\\Updater\\PackageDistribution',
            'App\\Models\\DistributedPackage',
            // Reseller revenue
            'App\\Services\\Platform\\PlatformEarningsService',
            'App\\Services\\Platform\\PlatformWithdrawalService',
        ];

        foreach ($forbidden as $fqcn) {
            $this->assertFalse(
                class_exists($fqcn),
                "$fqcn must NOT exist on a white-label build — it is issuer/oversight/distributor/reseller code that belongs only on the master platform (license boundary)."
            );
        }
    }

    public function test_no_issuer_or_distributor_route_is_registered_on_this_fork(): void
    {
        $forbidden = [
            'admin.white-label',            // oversight registry screen
            'admin.white-label.intake.pdf', // intake PDF export
            'merchant.white-label',         // self-service license SALES
            'white-label.register',         // issuer enrolment API
            'white-label.activate',         // issuer activation API
            'white-label.updates.check',    // distributor API
            'white-label.updates.download',
            'white-label.entitlement',
            'white-label.themes.check',
        ];

        foreach ($forbidden as $name) {
            $this->assertFalse(
                Route::has($name),
                "Route [$name] must NOT be registered on a white-label build (license boundary)."
            );
        }
    }

    public function test_the_consumer_leaf_survives(): void
    {
        // The fork must STILL be able to consume its license + pull updates and
        // gate its own features — removing these would be a different regression.
        $this->assertTrue(class_exists('App\\Services\\Updater\\WhiteLabelUpdateClient'), 'the update pull client must exist');
        $this->assertTrue(class_exists('App\\Support\\FeatureEntitlements'), 'the local feature gate must exist');
        $this->assertTrue(class_exists('App\\Livewire\\Admin\\WhiteLabelUpdater'), 'the consumer updater screen must exist');
        $this->assertTrue(Route::has('admin.updater'), 'the consumer updater route must exist');
    }
}
