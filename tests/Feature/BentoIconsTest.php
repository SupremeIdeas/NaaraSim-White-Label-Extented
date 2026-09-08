<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\BentoIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-managed 3D bento icons: each product card resolves an icon URL + opacity
 * from settings, falling back to the shipped /icons/*.webp default. Opacity is
 * clamped to 20–100 and the cache busts on any bento.* save.
 */
class BentoIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BentoIcons::flush();
    }

    public function test_it_falls_back_to_the_shipped_default_icon_and_opacity(): void
    {
        $this->assertSame('/icons/esim-data-plans.webp', BentoIcons::icon('esim-data-plans'));
        // Showcase cards default to an 80% watermark; tiles to full strength.
        $this->assertSame(80, BentoIcons::opacity('esim-data-plans'));
        $this->assertSame(100, BentoIcons::opacity('buy-esim'));
        $this->assertSame(0.8, BentoIcons::opacityFraction('esim-data-plans'));
    }

    public function test_tiles_ship_bold_and_scale_is_admin_adjustable_and_clamped(): void
    {
        // Action tiles ship bold (1.6×); showcase cards ship at 1.0×.
        $this->assertSame(1.6, BentoIcons::scale('buy-esim'));
        $this->assertSame(1.0, BentoIcons::scale('esim-data-plans'));

        Setting::setValue('bento.scale.get-number', 2.25, 'bento');
        BentoIcons::flush();
        $this->assertSame(2.25, BentoIcons::scale('get-number'));

        // Clamped to the 0.5–3.0 range.
        Setting::setValue('bento.scale.get-number', 9, 'bento');
        BentoIcons::flush();
        $this->assertSame(3.0, BentoIcons::scale('get-number'));
    }

    public function test_an_admin_upload_and_opacity_override_win(): void
    {
        Setting::setValue('bento.icon.naara-gift', 'https://cdn.example/gift.webp', 'bento');
        Setting::setValue('bento.opacity.naara-gift', 45, 'bento');
        BentoIcons::flush();

        $this->assertSame('https://cdn.example/gift.webp', BentoIcons::icon('naara-gift'));
        $this->assertSame(45, BentoIcons::opacity('naara-gift'));
    }

    public function test_opacity_is_clamped_to_the_supported_range(): void
    {
        Setting::setValue('bento.opacity.virtual-numbers', 5, 'bento');
        BentoIcons::flush();
        $this->assertSame(20, BentoIcons::opacity('virtual-numbers'));

        Setting::setValue('bento.opacity.virtual-numbers', 900, 'bento');
        BentoIcons::flush();
        $this->assertSame(100, BentoIcons::opacity('virtual-numbers'));
    }

    public function test_unknown_keys_are_safe(): void
    {
        $this->assertNull(BentoIcons::icon('not-a-card'));
        $this->assertSame(100, BentoIcons::opacity('not-a-card'));
    }
}
