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

    /**
     * PERMANENTLY MASTER-ONLY MODULES (owner decision 2026-10-03, same standing as the license boundary):
     * N2N (Naara-to-Naara) must never exist in a white-label build — no class, route, table, migration, view,
     * language file, config or test. If this fails, something N2N was ported or synced here: remove it, do not
     * relax this test. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md section 6 and CLAUDE.md.
     */
    public function test_no_master_only_module_files_exist_on_this_fork(): void
    {
        $roots = ['app', 'routes', 'database', 'resources', 'lang', 'config', 'tests', 'public/js', 'public/images'];
        $found = [];
        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                if ($rel === 'tests/Feature/WhiteLabelBoundaryFitnessTest.php') {
                    continue;
                }
                if (preg_match('#(^|[/_.\-])n2n([/_.\-]|[a-z]|$)#i', $rel) === 1) {
                    $found[] = $rel;
                }
                // Naara Widgets (merchant embed + WordPress loader) is master-only too: long distinctive tokens, matched anywhere.
                if (preg_match('#merchant[_-]?widget#i', $rel) === 1) {
                    $found[] = $rel;
                }
            }
        }
        $this->assertSame([], $found, 'N2N is permanently master-only and must not exist on a white-label build: '.implode(', ', array_slice($found, 0, 8)));
    }

    public function test_no_n2n_route_name_or_table_exists_on_this_fork(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringStartsNotWith('n2n.', (string) $route->getName(), 'a route named n2n.* must not exist on a white-label build');
            $this->assertStringStartsNotWith('merchant-widget', (string) $route->getName(), 'Naara Widgets routes are master-only');
        }
        foreach (\Illuminate\Support\Facades\Schema::getTableListing() as $table) {
            $this->assertDoesNotMatchRegularExpression('/(^|\.)n2n_/i', $table, 'a table named n2n_* must not exist on a white-label build');
            $this->assertDoesNotMatchRegularExpression('/merchant_widget/i', $table, 'Naara Widgets tables are master-only');
        }
    }

    /**
     * Skin allowance (Prompt 22): the ORIGINAL platform is the only authority for how many skins a licence unlocks. A fork stores the
     * one number master sends and obeys it (`LicensedSkins`); it must never carry the tier-to-count map or any config that could
     * mint an allowance locally. If this fails, authority code was copied here from master: remove it.
     */
    public function test_the_skin_allowance_authority_does_not_exist_on_this_fork_only_the_consumer_does(): void
    {
        $this->assertFalse(class_exists('App\\Support\\WhiteLabel\\SkinAllowance'), 'SkinAllowance (the tier-to-count authority) is master-only');
        $this->assertFalse(class_exists('App\\Support\\WhiteLabel\\EntitlementPayload'), 'EntitlementPayload (the issuer-side builder) is master-only');
        $this->assertFileDoesNotExist(config_path('white_label_skins.php'), 'the tier-to-count table is master-only config');
        $this->assertTrue(class_exists(\App\Support\Appearance\LicensedSkins::class), 'the consumer that obeys master\'s number must exist');
    }

    public function test_no_tier_name_to_skin_count_logic_lives_in_the_consumer(): void
    {
        $src = (string) file_get_contents(app_path('Support/Appearance/LicensedSkins.php'));
        $this->assertDoesNotMatchRegularExpression('/\b(extended|normal|basic|standard|premium)\b\s*=>\s*\d/i', $src, 'no tier => count map in the consumer');
        $this->assertStringNotContainsString('white_label_skins', $src);
    }
}
