<?php

namespace Tests\Feature;

use App\Support\DeepLinkLibrary;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tier 5 #11 Phase A2 — the sitewide deep-link picker every announcement and
 * broadcast-email composer reads from. Widened from the original 13 routes to
 * the full list the blueprint named. `merchant.white-label` (the White Label
 * license-purchase page) only exists as a registered route on master — the
 * License Surgery boundary removed it entirely from both white-label forks —
 * so the existing Route::has() guard in all() excludes it there automatically;
 * that structural fact is verified directly against each fork's routes/web.php
 * (not something a config flag on master can simulate, since routes are
 * compiled from the file at boot regardless of runtime config).
 */
class DeepLinkLibraryTest extends TestCase
{
    public function test_every_returned_link_is_a_real_route(): void
    {
        $links = DeepLinkLibrary::all();
        $this->assertNotEmpty($links);

        foreach ($links as $link) {
            $this->assertTrue(Route::has($link['key']), "route '{$link['key']}' does not exist");
            $this->assertNotEmpty($link['url']);
            $this->assertNotEmpty($link['label']);
        }
    }

    public function test_the_widened_destination_set_is_present_in_this_fork(): void
    {
        $keys = collect(DeepLinkLibrary::all())->pluck('key')->all();

        foreach ([
            'catalogue', 'numbers', 'gift-cards', 'wallet', 'profile', 'account',
            'journey', 'referrals', 'merchant.apply', 'brand.get-listed',
            'support', 'refund-policy',
        ] as $expected) {
            $this->assertContains($expected, $keys, "expected deep-link destination '{$expected}' missing");
        }
    }

    /**
     * The License Surgery boundary removed the White Label purchase route
     * entirely from this fork — Route::has() in all() excludes it
     * automatically, with nothing to drift out of sync.
     */
    public function test_the_white_label_purchase_link_is_absent_in_this_fork(): void
    {
        $keys = collect(DeepLinkLibrary::all())->pluck('key')->all();

        $this->assertNotContains('merchant.white-label', $keys);
    }

    public function test_no_link_key_appears_twice(): void
    {
        $keys = collect(DeepLinkLibrary::all())->pluck('key');
        $this->assertSame($keys->unique()->count(), $keys->count());
    }
}
