<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The public, browsable Developer API documentation page (ROADMAP §Layer 2),
 * rendered from the canonical docs/DEVELOPER-API.md reference.
 */
class DeveloperDocsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('developer-api-docs-html-v2');
    }

    public function test_the_developer_docs_page_is_public_and_renders_the_reference(): void
    {
        config(['app.url' => 'https://naarasim.example']);

        $res = $this->get('/developers')->assertOk();

        $res->assertSee('Developer API', false)
            ->assertSee('Build on NaaraSim', false)     // the page hero
            ->assertSee('Authentication', false)        // a reference heading
            ->assertSee('<table', false)                // GFM tables rendered
            ->assertSee('/api/v1/catalogue', false);    // an endpoint example

        // The base-URL placeholder is personalised to this install.
        $res->assertSee('naarasim.example', false)
            ->assertDontSee('YOUR-DOMAIN', false);
    }

    public function test_the_developers_page_is_linked_from_the_marketing_nav(): void
    {
        $this->get('/')->assertOk()->assertSee(route('developers'), false);
    }

    /**
     * §4 audit (2026-09-13): the page had zero in-page navigation for a
     * 270+ line single-page doc. The TOC is built server-side from the doc's
     * own `## ` headings, so it can never silently drift from the real
     * section list — every TOC link must resolve to a real heading anchor.
     */
    public function test_every_toc_link_resolves_to_a_real_heading_anchor(): void
    {
        $html = $this->get('/developers')->assertOk()->getContent();

        preg_match_all('/href="#([a-z0-9-]+)"/', $html, $tocLinks);
        preg_match_all('/<h2 id="([a-z0-9-]+)">/', $html, $headingIds);

        // The doc has 9 numbered top-level sections (Base URL through Quick
        // start) — assert the real count so a heading silently dropping from
        // the markdown (and its TOC entry with it) still fails loudly.
        $this->assertCount(9, $headingIds[1]);
        foreach ($headingIds[1] as $slug) {
            $this->assertContains($slug, $tocLinks[1], "TOC is missing a link to #{$slug}.");
        }
    }

    /** §4 audit finding: the real 409 (duplicate_in_progress) response was undocumented. */
    public function test_the_409_duplicate_order_response_is_documented(): void
    {
        $this->get('/developers')->assertOk()
            ->assertSee('409', false)
            ->assertSee('duplicate_in_progress', false);
    }

    public function test_the_developer_docs_page_never_leaks_a_literal_brand_color(): void
    {
        $html = $this->get('/developers')->assertOk()->getContent();

        // §4 audit finding: the inline <style> block hardcoded teal/gold as
        // raw rgb() literals — the §1.2 anti-pattern in a form the Blade
        // hex/utility-class scanner (bin/theme-token-audit.php) can't catch.
        $this->assertStringNotContainsString('rgb(10 110 110)', $html);
        $this->assertStringNotContainsString('rgb(45 212 191)', $html);
        $this->assertStringNotContainsString('rgb(212 160 23', $html);
    }
}
