<?php

namespace Tests\Feature\Appearance;

use App\Support\Appearance\AccentDeriver;
use PHPUnit\Framework\TestCase;

/** Any colour a member picks must come out readable (Prompt 20 §38), and match the wireframe's reference JavaScript exactly. */
class AccentDeriverTest extends TestCase
{
    private function rgb(string $triple): array
    {
        return array_map('intval', explode(' ', $triple));
    }

    public function test_five_hundred_random_colours_meet_the_contrast_targets_in_both_modes(): void
    {
        mt_srand(2026);
        $white = [255, 255, 255];
        for ($i = 0; $i < 500; $i++) {
            $hex = sprintf('#%02x%02x%02x', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            foreach (['dark', 'light'] as $mode) {
                $t = AccentDeriver::derive($hex, $mode);
                $surface = $mode === 'light' ? $white : [8, 32, 47];
                $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($white, $this->rgb($t['cta_b'])), "{$hex} {$mode}: white on cta_b");
                $this->assertGreaterThanOrEqual(3.0, AccentDeriver::contrast($white, $this->rgb($t['cta_a'])), "{$hex} {$mode}: white on cta_a");
                $this->assertGreaterThanOrEqual(4.5, AccentDeriver::contrast($this->rgb($t['teal_ink']), $surface), "{$hex} {$mode}: teal_ink on surface");
            }
        }
    }

    public function test_a_colour_that_is_already_readable_is_not_adjusted(): void
    {
        $t = AccentDeriver::derive('#0a6e6e', 'dark');
        $this->assertSame('10 110 110', $t['cta_b']);
        $this->assertFalse(AccentDeriver::derive('#0a3d3d', 'light')['adjusted'] ?? true);
    }

    public function test_hex_validation(): void
    {
        $this->assertTrue(AccentDeriver::isValidHex('#8b5cf6'));
        foreach (['#8B5CF6', '8b5cf6', '#8b5cf', '#8b5cf6f', null, '', '#gggggg'] as $bad) {
            $this->assertFalse(AccentDeriver::isValidHex($bad), json_encode($bad));
        }
    }

    public function test_php_matches_the_wireframes_javascript_on_fixed_colours(): void
    {
        $node = trim((string) shell_exec('command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed');
        }
        $html = file_get_contents(dirname(__DIR__, 3).'/docs/appearance/wireframes/naara-dashboard-system-v9-surface-core13.html');
        $this->assertSame(1, preg_match('/function hex2rgb.*?adjusted:[^\n]*\}\}\n/s', $html, $m), 'reference derive() not found in the wireframe');
        $colours = ['#8b5cf6', '#f43f5e', '#f59e0b', '#22c55e', '#06b6d4', '#0a6e6e', '#ffffff', '#000000', '#ffff00', '#808080', '#d4a017', '#ff00ff', '#123456', '#abcdef', '#e8412a', '#00ff7f', '#fafad2', '#2f4f4f', '#ff6347', '#7fffd4'];
        $script = $m[0].'for (const hex of '.json_encode($colours).') for (const mode of ["dark","light"]) { const t = derive(hex, mode); console.log(JSON.stringify([hex, mode, t.teal.join(" "), t.ctaA.join(" "), t.ctaB.join(" "), t.ink.join(" "), t.accC.join(" "), t.adjusted])); }';
        $file = tempnam(sys_get_temp_dir(), 'derive').'.js';
        file_put_contents($file, $script);
        $lines = array_filter(explode("\n", (string) shell_exec(escapeshellarg($node).' '.escapeshellarg($file).' 2>&1')));
        @unlink($file);

        $this->assertCount(40, $lines, 'node did not run the reference code');
        foreach ($lines as $line) {
            [$hex, $mode, $teal, $ctaA, $ctaB, $ink, $accC, $adjusted] = json_decode($line, true);
            $t = AccentDeriver::derive($hex, $mode);
            $this->assertSame([$teal, $ctaA, $ctaB, $ink, $accC, $adjusted], [$t['teal'], $t['cta_a'], $t['cta_b'], $t['teal_ink'], $t['acc_c'], $t['adjusted']], "{$hex} {$mode}");
        }
    }
}
