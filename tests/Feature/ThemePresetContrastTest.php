<?php

namespace Tests\Feature;

use App\Support\ColorContrast;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * color-system skill audit (2026-09-06): running every shipped preset
 * through contrast_check.py found `text-accent`-on-white/light-tint
 * failing WCAG on 18 of 20 presets, including naara-official's own gold
 * (2.38:1 — well under the 3:1 UI-component floor). Fixed by adding a
 * proper `accent_dark` token per preset. This test is the standing
 * guardrail so it never silently regresses again — for THIS preset set and
 * for every one Batch 3 (the 40-theme expansion) adds after it: any new
 * preset that ships without a WCAG-passing accent_dark, or whose primary
 * can't carry white button text, fails CI instead of shipping unnoticed.
 */
class ThemePresetContrastTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_preset_accent_dark_clears_text_contrast_on_white(): void
    {
        $this->seed(ThemePresetSeeder::class);

        $failures = [];
        foreach (DB::table('theme_presets')->get() as $row) {
            $tokens = json_decode($row->tokens, true);
            $accentDark = $tokens['colors']['accent_dark'] ?? null;
            $this->assertNotNull($accentDark, "{$row->slug} is missing accent_dark entirely.");

            $ratio = ColorContrast::ratio($accentDark, '#FFFFFF');
            if ($ratio < 4.5) {
                $failures[] = "{$row->slug}: accent_dark {$accentDark} only {$ratio}:1 against white (need 4.5:1)";
            }
        }

        $this->assertSame([], $failures, "Preset(s) with an accent_dark that fails WCAG normal-text contrast:\n".implode("\n", $failures));
    }

    public function test_every_seeded_preset_primary_carries_at_least_large_text_white(): void
    {
        $this->seed(ThemePresetSeeder::class);

        $failures = [];
        foreach (DB::table('theme_presets')->get() as $row) {
            $tokens = json_decode($row->tokens, true);
            $primary = $tokens['colors']['primary'] ?? null;
            $this->assertNotNull($primary, "{$row->slug} is missing primary entirely.");

            $ratio = ColorContrast::ratio($primary, '#FFFFFF');
            if ($ratio < 3.0) {
                $failures[] = "{$row->slug}: primary {$primary} only {$ratio}:1 against white (need >=3:1 for button/large text)";
            }
        }

        $this->assertSame([], $failures, "Preset(s) whose primary can't carry white button text:\n".implode("\n", $failures));
    }
}
