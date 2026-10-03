<?php

namespace Tests\Feature\Appearance;

use App\Support\Appearance\AccentDeriver;
use PHPUnit\Framework\TestCase;

/**
 * Prompt 20 §14: contrast of the canonical tokens is asserted, not assumed. Every text-on-surface pair, every accent's CTA label
 * (white on cta-a -> cta-b, 18px/700 = large text, 3:1) and the gold CTA, in both modes. If a pair fails, darken the token; never
 * loosen this test.
 */
class SurfaceTokenContrastTest extends TestCase
{
    /** @return array{light: array<string, array>, dark: array<string, array>} */
    private function tokens(): array
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/nx-tokens.css');
        $parse = function (string $block): array {
            preg_match_all('/--nx-([a-z0-9-]+):\s*(\d+) (\d+) (\d+);/', $block, $m, PREG_SET_ORDER);

            return collect($m)->mapWithKeys(fn ($x) => [$x[1] => [(int) $x[2], (int) $x[3], (int) $x[4]]])->all();
        };
        preg_match('/:root\s*\{(.*?)\n\}/s', $css, $light);
        preg_match('/\.dark\s*\{(.*?)\n\}/s', $css, $dark);
        $l = $parse($light[1]);

        return ['light' => $l, 'dark' => array_replace($l, $parse($dark[1]))];
    }

    public function test_text_on_surfaces_meets_wcag_in_both_modes(): void
    {
        foreach ($this->tokens() as $mode => $t) {
            foreach (['surface', 'surface-2', 'canvas'] as $bg) {
                foreach (['text' => 4.5, 'text-2' => 4.5, 'text-3' => 4.5, 'teal-ink' => 4.5, 'gold-ink' => 4.5] as $fg => $floor) {
                    $this->assertGreaterThanOrEqual($floor, AccentDeriver::contrast($t[$fg], $t[$bg]), "{$mode}: {$fg} on {$bg}");
                }
            }
            $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($t['ok'], $t['surface']), "{$mode}: ok on surface");
            $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($t['warn'], $t['surface']), "{$mode}: warn on surface");
            $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($t['bad'], $t['surface']), "{$mode}: bad on surface");
            $this->assertGreaterThanOrEqual(7.0, AccentDeriver::contrast($t['on-gold'], $t['gold']), "{$mode}: label on the gold CTA");
        }
    }

    public function test_every_accents_cta_label_is_readable_in_both_modes(): void
    {
        $cfg = require dirname(__DIR__, 3).'/config/appearance.php';
        $white = [255, 255, 255];
        foreach ($cfg['accents'] as $key => $a) {
            foreach (['dark', 'light'] as $mode) {
                $v = array_replace($a['dark'], $mode === 'light' ? $a['light'] : []);
                $to = fn (string $s) => array_map('intval', explode(' ', $s));
                $this->assertGreaterThanOrEqual(3.0, AccentDeriver::contrast($white, $to($v['cta_a'])), "{$key} {$mode}: white on cta_a");
                $this->assertGreaterThanOrEqual(3.0, AccentDeriver::contrast($white, $to($v['cta_b'])), "{$key} {$mode}: white on cta_b");
                $surface = $mode === 'light' ? $white : [8, 32, 47];
                $canvas = $mode === 'light' ? [235, 242, 244] : [3, 17, 30];
                $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($to($v['teal_ink']), $canvas), "{$key} {$mode}: teal_ink on the page canvas");
                $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($to($v['teal_ink']), $surface), "{$key} {$mode}: teal_ink on surface");
                // text sitting on the saturated primary card (acc-b), the on-fill tokens are white-ish
                $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($white, $to($v['acc_b'])), "{$key} {$mode}: white on acc_b (primary card)");
            }
        }
    }

    public function test_the_generated_css_is_in_step_with_the_config(): void
    {
        $cfg = require dirname(__DIR__, 3).'/config/appearance.php';
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/nx-accents.css');
        foreach ($cfg['accents'] as $key => $a) {
            $this->assertStringContainsString("[data-nx-accent={$key}]{--nx-teal:{$a['dark']['teal']};", $css, "{$key} missing or stale: run scripts/nx_accents.py");
        }
        $this->assertStringNotContainsString('--brand-accent', $css, 'accents never touch gold (money)');
    }
}
