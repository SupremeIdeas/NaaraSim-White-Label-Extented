<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * HOTFIX §8 — the brand logo sizes from a named `size` prop so it reads
 * consistently everywhere, instead of ad-hoc per-usage height classes.
 */
class BrandLogoSizingTest extends TestCase
{
    public function test_named_sizes_map_to_consistent_dimensions(): void
    {
        // The product (NaaraSim) wordmark is wide (~4:1) — the height map is a
        // stable reference for the named-size contract.
        $md = Blade::render('<x-brand-logo variant="product" size="md" />');
        $this->assertStringContainsString('h-8', $md);
        $this->assertStringContainsString('max-w-[170px]', $md);

        $lg = Blade::render('<x-brand-logo variant="product" size="lg" />');
        $this->assertStringContainsString('h-9', $lg);
        $this->assertStringContainsString('max-w-[190px]', $lg);

        $xl = Blade::render('<x-brand-logo variant="product" size="xl" />');
        $this->assertStringContainsString('md:h-28', $xl);
    }

    public function test_family_and_gift_marks_use_the_same_wide_map_as_product(): void
    {
        // The official Naara + Naara Gift marks are wordmarks too (~3.4:1 and
        // ~4.8:1) — the widest of the four brand marks — so all four share the
        // one height map, sized generously enough that none of them clip.
        $family = Blade::render('<x-brand-logo variant="family" size="md" />');
        $this->assertStringContainsString('h-8', $family);
        $this->assertStringContainsString('max-w-[170px]', $family);

        $gift = Blade::render('<x-brand-logo variant="gift" size="lg" />');
        $this->assertStringContainsString('h-9', $gift);
        $this->assertStringContainsString('max-w-[190px]', $gift);
    }

    public function test_it_defaults_to_md_and_class_is_for_spacing_on_the_wrapper(): void
    {
        $html = Blade::render('<x-brand-logo variant="product" class="mb-2" />');
        $this->assertStringContainsString('h-8', $html);   // default md
        $this->assertStringContainsString('mb-2', $html);  // spacing utility kept
    }
}
