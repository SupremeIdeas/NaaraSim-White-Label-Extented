<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\NavItemOverride;
use App\Models\User;
use App\Support\BottomNav;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-configurable bottom nav (owner request). With no override configured,
 * resolveMain()/resolveNumbers() must reproduce EXACTLY what customer.blade.php
 * and app-shell.blade.php used to build inline — this is a behavior-preserving
 * refactor, not a redesign, and these tests pin that down. Once an override
 * exists, it can reorder or hide eligible items, but can never expose
 * something the viewer wasn't already allowed to see.
 */
class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_main_nav_for_a_plain_user_matches_the_original_layout(): void
    {
        $user = User::factory()->create();

        $nav = BottomNav::resolveMain($user);

        // naara_gift ships "on by default" (FeatureFlags::FEATURES) so an
        // untouched install shows all 4: eSIMs · Numbers · Gifts · Wallet.
        $this->assertSame(['catalogue', 'numbers', 'gift-cards', 'wallet'], array_column($nav['primary'], 'route'));
        $this->assertSame('dashboard', $nav['more'][0]['route']);
        $this->assertSame('Home', $nav['more'][0]['label']);
        $this->assertSame('guide', end($nav['more'])['route']);
    }

    public function test_default_main_nav_for_a_guest_matches_the_original_layout(): void
    {
        $nav = BottomNav::resolveMain(null);

        $this->assertSame(['catalogue', 'numbers', 'gift-cards', 'wallet'], array_column($nav['primary'], 'route'));
        $this->assertSame('dashboard', $nav['more'][0]['route']);
    }

    public function test_a_v2_merchant_gets_clients_invoices_storefront_right_after_home(): void
    {
        $user = User::factory()->create();
        Merchant::create([
            'owner_user_id' => $user->id, 'business_name' => 'Test Biz', 'slug' => 'test-biz-'.$user->id,
            'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2,
        ]);

        $nav = BottomNav::resolveMain($user->fresh());

        $routes = array_column($nav['more'], 'route');
        $this->assertSame(['dashboard', 'merchant.clients', 'merchant.invoices', 'merchant.dashboard'], array_slice($routes, 0, 4));
    }

    public function test_an_admin_gets_the_admin_link_appended_before_the_trailing_guide_link(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $nav = BottomNav::resolveMain($user->fresh());

        $routes = array_column($nav['more'], 'route');
        $adminPos = array_search('admin.dashboard', $routes, true);
        $guidePos = array_search('guide', $routes, true);
        $this->assertNotFalse($adminPos);
        $this->assertLessThan($guidePos, $adminPos);
    }

    public function test_default_numbers_nav_matches_the_original_four_items_in_order(): void
    {
        $nav = BottomNav::resolveNumbers(unreadBadge: 3);

        $this->assertSame(
            ['numbers.contacts', 'numbers.forwarding', 'numbers.dialer', 'numbers.messages'],
            array_column($nav, 'route')
        );
        $this->assertSame(3, collect($nav)->firstWhere('route', 'numbers.messages')['badge']);
    }

    public function test_an_override_reorders_the_main_nav(): void
    {
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'wallet', 'position' => 0, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'catalogue', 'position' => 1, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'numbers', 'position' => 2, 'is_active' => true]);

        $nav = BottomNav::resolveMain(User::factory()->create());

        $this->assertSame('wallet', $nav['primary'][0]['route']);
    }

    public function test_hiding_a_primary_item_promotes_the_next_eligible_item_into_the_bar(): void
    {
        // Hide "wallet" (currently primary slot 3) — the next More item (Home)
        // should get pulled up into the primary bar, proving "replace" works.
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'wallet', 'position' => 0, 'is_active' => false]);

        $nav = BottomNav::resolveMain(User::factory()->create());

        $this->assertNotContains('wallet', array_column($nav['primary'], 'route'));
        $this->assertCount(4, $nav['primary']);
    }

    public function test_an_override_can_never_expose_an_item_the_viewer_is_not_eligible_for(): void
    {
        // A plain user has no merchant items in their eligible pool at all —
        // even if an admin's stored order lists merchant.dashboard first.
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'merchant.dashboard', 'position' => 0, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'catalogue', 'position' => 1, 'is_active' => true]);

        $nav = BottomNav::resolveMain(User::factory()->create());

        $allRoutes = array_merge(array_column($nav['primary'], 'route'), array_column($nav['more'], 'route'));
        $this->assertNotContains('merchant.dashboard', $allRoutes);
    }

    public function test_a_new_uncatalogued_override_key_is_dropped_gracefully(): void
    {
        NavItemOverride::create(['nav' => 'main', 'item_key' => 'some-future-route', 'position' => 0, 'is_active' => true]);

        $nav = BottomNav::resolveMain(User::factory()->create());

        $allRoutes = array_merge(array_column($nav['primary'], 'route'), array_column($nav['more'], 'route'));
        $this->assertNotContains('some-future-route', $allRoutes);
    }

    public function test_numbers_nav_override_can_swap_dialer_for_port_in(): void
    {
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.contacts', 'position' => 0, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.forwarding', 'position' => 1, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.messages', 'position' => 2, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.port-in', 'position' => 3, 'is_active' => true]);
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.dialer', 'position' => 4, 'is_active' => false]);

        $nav = BottomNav::resolveNumbers();

        $this->assertCount(4, $nav);
        $this->assertNotContains('numbers.dialer', array_column($nav, 'route'));
        $this->assertContains('numbers.port-in', array_column($nav, 'route'));
    }

    public function test_numbers_nav_always_returns_exactly_four_even_with_a_sparse_override(): void
    {
        NavItemOverride::create(['nav' => 'numbers', 'item_key' => 'numbers.messages', 'position' => 0, 'is_active' => true]);

        $nav = BottomNav::resolveNumbers();

        $this->assertCount(4, $nav);
    }
}
