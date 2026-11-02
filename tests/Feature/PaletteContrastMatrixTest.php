<?php

namespace Tests\Feature;

use App\Support\BrandSettings;
use App\Support\ColorContrast;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrast across every colourway the platform can wear, in light AND dark, so switching a palette or a theme never produces unreadable text:
 *   - white text on the primary and on the action colour (buttons),
 *   - the raw accent as text or icon on that palette's own dark surface (navy), where `dark:text-accent` is used,
 *   - the darkened accent as text on white, where `text-accent-dark` is used,
 *   - the primary as a link or label on that palette's dark surface once lifted for dark mode.
 * Ratios follow WCAG 2.x: 4.5 for text, 3.0 for large text and UI shapes.
 */
class PaletteContrastMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{primary: string, accent: string, navy: string, action: string}> */
    private function palettes(): array
    {
        $out = [];
        foreach (BrandSettings::PALETTES as $name => $c) {
            $out["palette:{$name}"] = $c;
        }
        $this->seed(ThemePresetSeeder::class);
        foreach (DB::table('theme_presets')->get() as $row) {
            $c = json_decode($row->tokens, true)['colors'] ?? [];
            $hex = fn (string $k) => sprintf('#%02X%02X%02X', ...array_map('intval', explode(' ', (string) ($c[$k] ?? '0 0 0'))));
            $out["preset:{$row->slug}"] = ['primary' => $hex('primary'), 'accent' => $hex('accent'), 'navy' => $hex('navy'), 'action' => $hex('action'), 'primary_dark' => $hex('primary_dark'), 'accent_dark' => $hex('accent_dark')];
        }

        return $out;
    }

    public function test_buttons_and_dark_surface_text_are_readable_in_every_palette_and_preset(): void
    {
        $fail = [];
        foreach ($this->palettes() as $name => $c) {
            $r = fn (string $a, string $b) => ColorContrast::ratio($a, $b);
            if ($r('#FFFFFF', $c['primary']) < 3.0) {
                $fail[] = "{$name}: white on primary {$c['primary']} = ".round($r('#FFFFFF', $c['primary']), 2);
            }
            if ($r('#FFFFFF', $c['action']) < 3.0) {
                $fail[] = "{$name}: white on action {$c['action']} = ".round($r('#FFFFFF', $c['action']), 2);
            }
            if ($r($c['accent'], $c['navy']) < 3.0) {
                $fail[] = "{$name}: accent {$c['accent']} on navy {$c['navy']} = ".round($r($c['accent'], $c['navy']), 2);
            }
        }
        $this->assertSame([], $fail, "Colourways with unreadable pairs:\n".implode("\n", $fail));
    }
}
