<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\FeatureEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The fork's public pages read like the licensee's own site: no vendor leftovers, no setup talk, no advertising of features the licence does not include. */
class ForkMarketingCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FeatureEntitlements::bust();
        parent::tearDown();
    }

    private function lock(array $keys): void
    {
        config(['updater.product_identifier' => 'naarasim-whitelabel']);
        Setting::setValue(FeatureEntitlements::STORE_KEY, $keys, 'white_label');
        FeatureEntitlements::bust();
    }

    public function test_extended_advertises_everything_it_includes(): void
    {
        $this->assertStringContainsString('/gift-cards', $this->get('/')->assertOk()->getContent());
    }

    public function test_the_faq_is_the_licensees_own_and_has_no_family_brand_riddle(): void
    {
        $html = strip_tags($this->get('/')->assertOk()->getContent());
        $this->assertStringContainsString('Does my phone support eSIM?', $html);
        $this->assertStringNotContainsString('how is it different from', $html);
        $this->assertStringNotContainsString('family brand', $html);
    }

    public function test_about_and_footer_never_print_the_brand_as_its_own_operator(): void
    {
        $about = strip_tags($this->get('/about')->assertOk()->getContent());
        $this->assertDoesNotMatchRegularExpression('/(\w+) is operated by \1\b/', $about);
        $this->assertDoesNotMatchRegularExpression('/Made with love by (\w+)\./', $about);
    }

    public function test_pricing_and_contact_do_not_talk_about_setup(): void
    {
        $this->assertStringNotContainsString('catalogue is connected', $this->get('/pricing')->assertOk()->getContent());
        $this->assertStringNotContainsString('being set up', $this->get('/contact')->assertOk()->getContent());
    }

    public function test_the_login_panel_speaks_in_the_licensees_tagline_not_the_vendors(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('No Borders. No Swaps', $html);
    }

    public function test_no_public_page_links_to_the_faq_page_a_white_label_does_not_have(): void
    {
        foreach (['/', '/about', '/contact', '/pricing', '/login'] as $path) {
            $this->assertStringNotContainsString('href="'.url('/faq').'"', $this->get($path)->assertOk()->getContent(), $path);
        }
        $this->get('/faq')->assertNotFound();
    }
}
