<?php

namespace Tests\Feature;

use App\Services\Builder\PageBuilderService;
use App\Support\PageSections;
use App\Support\SectionLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BLUEPRINT-batch1-sections §7 — the storytelling carousel registered as a
 * page-builder block type an admin can drop into any custom page.
 */
class StorytellingBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_storytelling_is_a_registered_block_with_a_slides_repeater(): void
    {
        $this->assertTrue(SectionLibrary::has('storytelling'));
        $this->assertSame('partials.sections.storytelling', SectionLibrary::bladeFor('storytelling'));

        $repeater = SectionLibrary::repeaterFor('storytelling');
        $this->assertSame('slides', $repeater['field']);
        $this->assertSame('image', $repeater['imageKey']);
        // Defaults ship with example slides.
        $this->assertNotEmpty(SectionLibrary::defaultsFor('storytelling')['slides']);
    }

    public function test_a_published_storytelling_block_renders_the_carousel_on_the_public_page(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('home', 'storytelling');
        $svc->updateConfig($section, [
            'heading' => 'STORY_BLOCK_MARKER',
            'tone' => 'on-dark',
            'slides' => [
                ['image' => '', 'eyebrow' => 'Beat one', 'title' => 'A built slide', 'body' => 'Rendered by the page builder.', 'cta_label' => 'Go', 'cta_target' => '/catalogue', 'modal_body' => 'Deeper copy for the modal.'],
                ['image' => '', 'eyebrow' => '', 'title' => 'Second built slide', 'body' => 'Second beat.', 'modal_body' => ''],
            ],
        ]);
        $svc->publish('home');
        PageSections::flush('home');

        $this->get('/')->assertOk()
            ->assertSee('STORY_BLOCK_MARKER', false)          // section heading
            ->assertSee('storytellingCarousel(', false)       // the reusable carousel
            ->assertSee('A built slide', false)               // server-rendered first slide
            ->assertSee('Deeper copy for the modal.', false); // slide modal_body → content modal
    }
}
