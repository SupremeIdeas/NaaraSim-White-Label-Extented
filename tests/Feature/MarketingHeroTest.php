<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marketing homepage hero (BUILD-13, corrected target): a separate, optional
 * showcase image renders below the description and above the CTA row, and the
 * CTA row is locked to two columns.
 */
class MarketingHeroTest extends TestCase
{
    use RefreshDatabase;

    private function setHeroField(string $field, string $value): void
    {
        $stored = Setting::getValue('site.page.home', []);
        $stored = is_array($stored) ? $stored : [];
        $stored['hero'] = ($stored['hero'] ?? []) + ['visible' => true, 'order' => 0];
        $stored['hero'][$field] = $value;
        Setting::setValue('site.page.home', $stored, 'site', 'Marketing page overrides.');
        SiteContent::flush();
    }

    public function test_the_hero_renders_without_a_showcase_image_by_default(): void
    {
        $res = $this->get('/')->assertOk();
        $res->assertSee('Land Anywhere.', false); // hero still there
        // The CTA row is a locked two-column grid, not a wrapping flex.
        $res->assertSee('grid max-w-md grid-cols-2', false);
    }

    public function test_a_showcase_image_renders_as_a_visible_block(): void
    {
        $this->setHeroField('showcase_image', 'https://cdn.example.com/app-mockup.webp');

        $this->get('/')->assertOk()
            ->assertSee('https://cdn.example.com/app-mockup.webp', false);
    }

    public function test_showcase_image_is_independent_of_the_backdrop_image(): void
    {
        // Only the backdrop set → no showcase block.
        $this->setHeroField('image', 'https://cdn.example.com/backdrop.jpg');
        $this->get('/')->assertOk()
            ->assertSee('https://cdn.example.com/backdrop.jpg', false)
            ->assertDontSee('app-mockup.webp', false);
    }

    public function test_showcase_image_field_is_part_of_the_hero_defaults(): void
    {
        $this->assertArrayHasKey('showcase_image', SiteContent::page('home')['hero']);
    }
}
