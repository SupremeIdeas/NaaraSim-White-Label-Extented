<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-12 — the "Who Naara Is For" homepage audience-tabs section. Registered
 * as a real, admin-orderable homepage section (not a hardcoded insert),
 * defaulted between products and features, with the six approved panels.
 */
class HomepageAudiencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_six_audience_panels(): void
    {
        $res = $this->get('/')->assertOk();

        // Section header + a sample of each panel's verbatim copy.
        $res->assertSee('Who Naara Is For', false);
        $res->assertSee('Travel Smarter. Stay Connected Everywhere.', false);
        $res->assertSee('Power Your Business Across Borders', false);
        $res->assertSee('Build Without Boundaries', false);
        $res->assertSee('Create Without Losing Connection', false);
        $res->assertSee('Protect Your Identity Online', false);
        $res->assertSee('Stay Close Across Every Border', false);
    }

    public function test_audiences_is_a_registered_orderable_section_after_products(): void
    {
        $sections = SiteContent::page('home', includeHidden: true);

        $this->assertArrayHasKey('audiences', $sections);
        // Orderable like any section: carries visible + order.
        $this->assertArrayHasKey('order', $sections['audiences']);
        $this->assertTrue($sections['audiences']['visible']);
        // Default position: after products.
        $this->assertGreaterThan($sections['products']['order'], $sections['audiences']['order']);
    }

    public function test_the_panel_images_are_admin_swappable_and_default_to_shipped_webp(): void
    {
        $defaults = SiteContent::defaults()['home']['audiences'];

        $this->assertSame('/images/audiences/naara-leisure-traveler.webp', $defaults['travelers_image']);
        $this->assertArrayHasKey('families_image', $defaults);
    }
}
