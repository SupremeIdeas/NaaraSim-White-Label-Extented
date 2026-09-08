<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * BLUEPRINT-batch1-sections §3 — the reusable storytelling carousel + the
 * generic content modal it opens.
 */
class StorytellingCarouselTest extends TestCase
{
    private array $slides = [
        [
            'image' => 'https://cdn.naarasim.com/a.webp',
            'eyebrow' => 'Data & connectivity',
            'title' => 'Naara Data',
            'body' => 'eSIM data plans for 190+ countries, live in twelve seconds.',
            'cta_label' => 'Explore Naara Data',
            'cta_url' => 'https://example.com/catalogue',
            'modal_blocks' => [['heading' => 'The friction', 'text' => 'A $40 airport SIM.']],
            'modal_gallery' => ['https://cdn.naarasim.com/g1.webp'],
        ],
        [
            'image' => 'https://cdn.naarasim.com/b.webp',
            'eyebrow' => 'Verification',
            'title' => 'Naara Verify',
            'body' => 'OTP numbers for the apps that ask for a code.',
        ],
    ];

    public function test_carousel_renders_slides_nav_and_alpine_wiring(): void
    {
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="products" />', [
            'slides' => $this->slides,
        ]);

        // Alpine component is wired with the right shape.
        $this->assertStringContainsString('storytellingCarousel(', $html);
        $this->assertStringContainsString("key: 'products'", $html);
        // Both images render in the stacked crossfade layer.
        $this->assertStringContainsString('nx-story__img', $html);
        $this->assertStringContainsString('cdn.naarasim.com/a.webp', $html);
        $this->assertStringContainsString('cdn.naarasim.com/b.webp', $html);
        // Progress dots (one per slide) + play/pause control.
        $this->assertStringContainsString('goTo(0)', $html);
        $this->assertStringContainsString('goTo(1)', $html);
        $this->assertStringContainsString('toggle()', $html);
    }

    public function test_read_seconds_default_is_word_count_over_three_with_a_floor(): void
    {
        // A long body → seconds = ceil(words/3); a short one → floored at 4.
        $slides = [
            ['title' => 'Long', 'image' => 'x', 'body' => str_repeat('word ', 30)], // 30 words → 10s
            ['title' => 'Short', 'image' => 'y', 'body' => 'two words'],            // 2 words → floor 4s
        ];
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="s" />', ['slides' => $slides]);

        $this->assertStringContainsString("readSeconds: JSON.parse('[10,4]')", $html);
    }

    public function test_fab_and_content_modal_only_render_for_slides_with_modal_content(): void
    {
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="products" />', [
            'slides' => $this->slides,
        ]);

        // Slide 0 has modal content → a content modal named products-0 exists…
        $this->assertStringContainsString("name: modalName()", $html);
        $this->assertStringContainsString("=== 'products-0'", $html); // x-ui.modal open-guard
        // …slide 1 has none → no products-1 modal.
        $this->assertStringNotContainsString("=== 'products-1'", $html);
        // Modal reuses the ONE engine + shows the gallery image + block text.
        $this->assertStringContainsString('cdn.naarasim.com/g1.webp', $html);
        $this->assertStringContainsString('The friction', $html);
    }

    public function test_empty_slides_render_nothing(): void
    {
        $html = Blade::render('<x-storytelling-carousel :slides="[]" section-key="x" />');
        $this->assertStringNotContainsString('storytellingCarousel(', $html);
    }

    public function test_text_only_slides_drop_the_image_column(): void
    {
        // No slide has an image → no image stage, centred text (About case).
        $slides = [
            ['title' => 'Why we exist', 'body' => 'A mission.'],
            ['title' => 'Where we head', 'body' => 'A vision.'],
        ];
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="about-essence" tone="on-dark" />', ['slides' => $slides]);

        $this->assertStringNotContainsString('nx-story__stage', $html); // no image well
        $this->assertStringContainsString('text-center', $html);        // centred
        // on-dark tone forces light text.
        $this->assertStringContainsString('text-white', $html);
    }

    public function test_about_page_renders_the_essence_carousel(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('storytellingCarousel(', false)
            ->assertSee("key: 'about-essence'", false)
            ->assertSee('Naara means dawn'); // Essence slide title (server-rendered first slide)
    }

    public function test_first_slide_is_server_rendered_for_no_js_and_crawlers(): void
    {
        $slides = [
            ['title' => 'Naara Data', 'body' => 'eSIM data plans, 190+ countries.', 'eyebrow' => 'Data'],
            ['title' => 'Second', 'body' => 'Hidden until JS.'],
        ];
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="p" />', ['slides' => $slides]);

        // The first slide's real text is in the DOM (not only in the x-data payload).
        $this->assertStringContainsString('>Naara Data</h2>', $html);
        $this->assertStringContainsString('eSIM data plans, 190+ countries.</p>', $html);
    }

    public function test_section_nav_is_gated_by_the_shared_store(): void
    {
        $slides = [['title' => 'A', 'body' => 'a'], ['title' => 'B', 'body' => 'b']];
        $html = Blade::render('<x-storytelling-carousel :slides="$slides" section-key="p" />', ['slides' => $slides]);

        // §6 mutual exclusion: the section nav shows only when its section is active.
        $this->assertStringContainsString('$store.sectionNav.isActive(key)', $html);
    }
}
