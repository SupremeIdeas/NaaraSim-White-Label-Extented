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
        Cache::forget('developer-api-docs-html');
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
}
