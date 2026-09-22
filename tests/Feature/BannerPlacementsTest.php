<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BannerPlacements;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Frontend-UX-fix blueprint Phase G — generalizes the homepage's own "More
 * from Naara" banner carousel into an admin-configurable placement system
 * (marketing_home/dashboard_footer placements, banner_only/
 * banner_with_description display styles), reusing the existing
 * `<x-storytelling-carousel>` component. Zero-behavior-change by default.
 */
class BannerPlacementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_home_defaults_to_the_pre_existing_behaviour(): void
    {
        $config = BannerPlacements::config('marketing_home');

        $this->assertTrue($config['enabled']);
        $this->assertSame('banner_with_description', $config['display_style']);
    }

    public function test_dashboard_footer_is_off_by_default_zero_behaviour_change(): void
    {
        $config = BannerPlacements::config('dashboard_footer');

        $this->assertFalse($config['enabled']);
    }

    public function test_the_legacy_setting_key_still_controls_marketing_home(): void
    {
        \App\Models\Setting::setValue('home.banner_carousel_enabled', false);

        $config = BannerPlacements::config('marketing_home');

        $this->assertFalse($config['enabled']);
    }

    public function test_the_homepage_still_shows_the_banner_carousel_by_default(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $res->assertSee('home-banners', false);
    }

    public function test_the_homepage_banner_carousel_can_still_be_disabled_via_the_legacy_key(): void
    {
        \App\Models\Setting::setValue('home.banner_carousel_enabled', false);

        $res = $this->get('/');
        $res->assertOk();
        $res->assertDontSee('home-banners', false);
    }

    public function test_the_dashboard_shows_no_footer_banner_until_an_admin_enables_it(): void
    {
        $user = User::factory()->create()->fresh();

        $res = $this->actingAs($user)->get('/dashboard');
        $res->assertOk();
        $res->assertDontSee('data-banner-placement="dashboard_footer"', false);

        BannerPlacements::save('dashboard_footer', true, 'banner_only');

        $res = $this->actingAs($user)->get('/dashboard');
        $res->assertOk();
        $res->assertSee('data-banner-placement="dashboard_footer"', false);
    }

    public function test_banner_only_display_style_drops_eyebrow_body_and_cta(): void
    {
        $slides = BannerPlacements::slides('banner_only');

        $this->assertNotEmpty($slides);
        foreach ($slides as $slide) {
            $this->assertArrayNotHasKey('eyebrow', $slide);
            $this->assertArrayNotHasKey('body', $slide);
            $this->assertArrayNotHasKey('cta_label', $slide);
            $this->assertArrayHasKey('image', $slide);
            $this->assertArrayHasKey('title', $slide);
        }
    }

    public function test_banner_with_description_keeps_the_full_slide_content(): void
    {
        $slides = BannerPlacements::slides('banner_with_description');

        $this->assertNotEmpty($slides);
        $this->assertArrayHasKey('eyebrow', $slides[0]);
        $this->assertArrayHasKey('body', $slides[0]);
        $this->assertArrayHasKey('cta_label', $slides[0]);
    }

    public function test_an_admin_can_configure_a_placement(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\BannerPlacements::class)
            ->call('toggle', 'dashboard_footer')
            ->assertHasNoErrors();

        $this->assertTrue(BannerPlacements::config('dashboard_footer')['enabled']);
    }

    public function test_the_admin_screen_is_role_gated(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(); // no admin role

        Livewire::actingAs($user)->test(\App\Livewire\Admin\BannerPlacements::class)
            ->assertForbidden();
    }
}
