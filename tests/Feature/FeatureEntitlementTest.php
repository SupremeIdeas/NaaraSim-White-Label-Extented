<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhiteLabelInstance;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use App\Services\Updater\WhiteLabelLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch 8 — Feature-Entitlement Gating. The MASTER decides, per level, which
 * features are locked (FeatureLocks); a fork ENFORCES its received list
 * (FeatureEntitlements). This proves the authority + the resolver, and the
 * belt-and-suspenders that the master itself is never gated.
 */
class FeatureEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WhiteLabelLicenseService
    {
        return app(WhiteLabelLicenseService::class);
    }

    protected function tearDown(): void
    {
        FeatureLocks::bust();
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    // --- entitlement_level is set at issuance from the tier ---

    public function test_issuing_an_extended_license_starts_fully_unlocked(): void
    {
        $instance = $this->service()->register(['brand_name' => 'X', 'contact_email' => 'x@x.test']);
        $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_EXTENDED);

        $this->assertSame(WhiteLabelInstance::LEVEL_FULL, $instance->fresh()->entitlement_level);
        $this->assertSame([], FeatureLocks::locksFor($instance->fresh()->entitlement_level));
    }

    public function test_issuing_a_normal_license_starts_locked_down_at_basic(): void
    {
        $instance = $this->service()->register(['brand_name' => 'Y', 'contact_email' => 'y@y.test']);
        $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);

        $this->assertSame(WhiteLabelInstance::LEVEL_BASIC, $instance->fresh()->entitlement_level);
        // Owner spec: basic locks gift cards, full voice eSIM, preloader, brand hunt.
        $this->assertEqualsCanonicalizing(
            [FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_ESIM_VOICE, FeatureLocks::F_PRELOADER, FeatureLocks::F_BRAND_HUNT],
            FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_BASIC)
        );
    }

    public function test_raising_a_normal_fork_to_standard_unlocks_all_but_gift_cards(): void
    {
        $instance = $this->service()->register(['brand_name' => 'Z', 'contact_email' => 'z@z.test']);
        $this->service()->issueLicense($instance, WhiteLabelInstance::TIER_NORMAL);

        $this->service()->setEntitlementLevel($instance, WhiteLabelInstance::LEVEL_STANDARD);

        $this->assertSame(WhiteLabelInstance::LEVEL_STANDARD, $instance->fresh()->entitlement_level);
        $this->assertSame([FeatureLocks::F_GIFT_CARDS], FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_STANDARD));
    }

    // --- FeatureLocks authority ---

    public function test_locks_are_strictly_nested_richer_level_locks_a_subset(): void
    {
        $basic = FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_BASIC);
        $standard = FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_STANDARD);
        $full = FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_FULL);

        $this->assertEmpty(array_diff($standard, $basic), 'standard locks ⊆ basic locks');
        $this->assertEmpty(array_diff($full, $standard), 'full locks ⊆ standard locks');
        $this->assertSame([], $full);
    }

    public function test_an_unknown_or_null_level_locks_nothing(): void
    {
        $this->assertSame([], FeatureLocks::locksFor(null));
        $this->assertSame([], FeatureLocks::locksFor('nonsense'));
    }

    public function test_admin_can_edit_a_levels_lock_list_and_it_is_catalog_filtered(): void
    {
        FeatureLocks::saveLevel(WhiteLabelInstance::LEVEL_STANDARD, [FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_BRAND_HUNT, 'not_a_real_feature']);

        $locks = FeatureLocks::locksFor(WhiteLabelInstance::LEVEL_STANDARD);
        $this->assertEqualsCanonicalizing([FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_BRAND_HUNT], $locks);
        $this->assertNotContains('not_a_real_feature', $locks);
    }

    // --- FeatureEntitlements enforcement (fork vs master) ---

    public function test_the_master_platform_never_locks_a_feature(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        // Even with a stray stored lock list, the master short-circuits to unlocked.
        Setting::setValue(FeatureEntitlements::STORE_KEY, [FeatureLocks::F_GIFT_CARDS]);
        FeatureEntitlements::bust();

        $this->assertFalse(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
        $this->assertSame([], FeatureEntitlements::all());
    }

    public function test_a_fork_with_no_received_list_yet_fails_open(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
        FeatureEntitlements::bust();

        // Fresh fork / master unreachable — nothing stored → nothing locked.
        $this->assertFalse(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
    }

    public function test_a_fork_enforces_the_list_it_received(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS, FeatureLocks::F_ESIM_VOICE]);

        $this->assertTrue(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
        $this->assertTrue(FeatureEntitlements::locked(FeatureLocks::F_ESIM_VOICE));
        $this->assertFalse(FeatureEntitlements::locked(FeatureLocks::F_BRAND_HUNT));
        $this->assertTrue(FeatureEntitlements::allowed(FeatureLocks::F_BRAND_HUNT));
    }

    public function test_store_is_a_no_op_on_the_master(): void
    {
        config()->set('updater.product_identifier', 'naarasim-core');
        FeatureEntitlements::store([FeatureLocks::F_GIFT_CARDS]);

        $this->assertSame([], FeatureEntitlements::all());
    }
}
