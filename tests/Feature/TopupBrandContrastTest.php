<?php

namespace Tests\Feature;

use App\Support\Topup\TopupBrandTint;
use Tests\TestCase;

/** The colour discipline of the top-up hero and offers: whatever a network's colour is, the ink on it, and the pills and buttons built from it, read at WCAG AA (4.5:1). */
class TopupBrandContrastTest extends TestCase
{
    /** @return list<string> every colour the app can ask for: researched families, logo-read colours, fixes and a spread of awkward ones. */
    private function allTints(): array
    {
        $all = ['#FFFFFF', '#000000', '#FFC400', '#FFD400', '#FCCC0C', '#E4002B', '#54B454', '#808080', '#767676', '#777777', '#1A4FBA', '#00A99D', '#F39200', '#7B2D8E', '#0A6E6E'];
        foreach (['topup-brand-families.json' => true, 'topup-brand-colors.json' => false] as $f => $nested) {
            $d = (array) json_decode((string) file_get_contents(base_path('database/seeders/'.$f)), true);
            foreach ($d as $v) {
                $all[] = $nested ? $v['hex'] : $v;
                foreach ($nested ? ($v['countries'] ?? []) : [] as $c) {
                    $all[] = $c;
                }
            }
        }

        return array_values(array_unique(array_map('strtoupper', $all)));
    }

    public function test_the_ink_on_any_brand_colour_is_at_least_aa(): void
    {
        foreach ($this->allTints() as $hex) {
            $hex = TopupBrandTint::safe($hex);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $hex);
            $this->assertGreaterThanOrEqual(4.5, TopupBrandTint::contrast($hex, TopupBrandTint::ink($hex)), "$hex has no readable ink");
        }
    }

    public function test_every_hero_palette_keeps_its_ink_readable_on_the_base_and_both_aurora_tones_in_both_modes(): void
    {
        foreach ($this->allTints() as $hex) {
            $pal = TopupBrandTint::palette($hex);
            foreach (['light', 'dark'] as $mode) {
                $p = $pal[$mode];
                foreach (['base', 'a1', 'a2'] as $k) {
                    $this->assertGreaterThanOrEqual($mode === 'dark' ? 6.9 : 5.4, TopupBrandTint::contrast($p[$k], $p['ink']), "$hex $mode $k ({$p[$k]}) vs {$p['ink']}");
                }
            }
            $this->assertSame('#FFFFFF', $pal['dark']['ink'], 'dark mode always uses light text');
        }
    }

    public function test_the_dark_aurora_is_the_brand_mixed_with_black_and_the_light_one_is_the_brand_lifted_toward_white(): void
    {
        $mtn = TopupBrandTint::palette('#FFC400');
        $this->assertGreaterThan(TopupBrandTint::luminance($mtn['light']['base']), TopupBrandTint::luminance($mtn['light']['a1']) + 0.0001, 'light aurora lifts the brand');
        $this->assertLessThan(0.2, TopupBrandTint::luminance($mtn['dark']['a1']), 'the dark aurora stays dark enough for white text');
        $this->assertGreaterThan(TopupBrandTint::luminance($mtn['dark']['base']), TopupBrandTint::luminance($mtn['dark']['a1']), 'but it still carries the brand');
        $this->assertSame('#0A0D10', $mtn['light']['ink'], 'dark ink on MTN yellow');
    }

    public function test_the_card_gradient_never_makes_the_ink_harder_to_read(): void
    {
        foreach ($this->allTints() as $hex) {
            $hex = TopupBrandTint::safe($hex);
            $ink = TopupBrandTint::ink($hex);
            $end = TopupBrandTint::gradientEnd($hex);
            $this->assertGreaterThanOrEqual(4.5, TopupBrandTint::contrast($end, $ink), "$hex -> $end loses contrast with $ink");
            $this->assertGreaterThanOrEqual(TopupBrandTint::contrast($hex, $ink) - 0.01, TopupBrandTint::contrast($end, $ink));
        }
    }

    public function test_every_lookup_returns_a_clean_hex_and_the_family_resolver_strips_country_and_product_words(): void
    {
        $this->assertSame('orange', TopupBrandTint::family('Orange Liberia Bundle', 'LR'));
        $this->assertSame('claro', TopupBrandTint::family('Claro Costa Rica', 'CR'));
        $this->assertSame('digicel', TopupBrandTint::family('Digicel Jamaica', 'JM'));
        foreach (['MTN Nigeria' => 'NG', 'Totally Unknown Telco' => 'NG', 'Airtel (Data)' => null, '' => null] as $n => $c) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', TopupBrandTint::for($n, $c));
        }
    }

    public function test_the_hero_and_offer_views_use_no_hard_coded_colours_beyond_brand_data(): void
    {
        foreach (['resources/views/livewire/partials/topup/_hero.blade.php', 'resources/views/livewire/partials/topup/_offers.blade.php'] as $f) {
            $src = (string) file_get_contents(base_path($f));
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b(?![^<]*\$)/', preg_replace('/\{\{.*?\}\}|@php.*?@endphp|<script.*?<\/script>/s', '', $src), "$f carries a hard-coded colour");
        }
    }
}
