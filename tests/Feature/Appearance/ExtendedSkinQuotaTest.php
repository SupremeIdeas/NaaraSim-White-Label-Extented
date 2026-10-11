<?php

namespace Tests\Feature\Appearance;

use App\Support\Appearance\LicensedSkins;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;

/**
 * Prompt 22 decision D2: Extended has ZERO feature locks, and its skin limit is a quota in a separate field. Proves the quota never
 * became a feature lock, and that the allowance is obeyed as a hard ceiling.
 */
class ExtendedSkinQuotaTest extends AppearanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['appearance.built' => ['surface', 'glass', 'neo', 'passport', 'golden', 'calm', 'ledger']]);
        config()->set('updater.product_identifier', 'naarasim-whitelabel-extended');
        FeatureEntitlements::bust();
    }

    public function test_a_five_skin_allowance_does_not_add_a_feature_lock(): void
    {
        $this->assertTrue(LicensedSkins::storeAllowance(5));

        $this->assertSame(5, LicensedSkins::allowance());
        $this->assertSame([], FeatureEntitlements::all());
        $this->assertFalse(FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS));
    }

    public function test_five_skins_are_accepted_and_a_sixth_is_rejected(): void
    {
        LicensedSkins::storeAllowance(5);

        $this->assertTrue(LicensedSkins::saveSelection(['surface', 'glass', 'neo', 'passport', 'golden'])['ok']);
        $this->assertSame(5, count(LicensedSkins::available()));
        $this->assertFalse(LicensedSkins::saveSelection(['surface', 'glass', 'neo', 'passport', 'golden', 'calm'])['ok']);
        $this->assertSame(5, count(LicensedSkins::chosen()), 'the rejected save changed nothing');
    }
}
