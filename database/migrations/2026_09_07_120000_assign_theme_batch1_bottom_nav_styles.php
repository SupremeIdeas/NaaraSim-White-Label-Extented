<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Theme visual rebuild, Batch 1 follow-up (owner request, 2026-09-07): the
 * bottom-nav section was extracted and given the same 5 style families as
 * header/login (see 2026_09_07_110000_assign_theme_batch1_section_styles.php),
 * one commit later — so it needs its own additive backfill rather than
 * amending that already-shipped migration. Same discipline: only fills a
 * currently-absent 'bottom_nav' key, never overwrites admin tuning, and the
 * seeder carries the identical assignment for fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->assignments() as $slug => $style) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            if (! array_key_exists('bottom_nav', $current)) {
                $current['bottom_nav'] = $style;
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->assignments() as $slug => $style) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            if (($current['bottom_nav'] ?? null) === $style) {
                unset($current['bottom_nav']);
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => $current === [] ? null : json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, string> */
    private function assignments(): array
    {
        return [
            'aries-contrast' => 'aries-contrast',
            'midnight-signal' => 'midnight-signal',
            'neon-vertex' => 'neon-vertex',
            'paperwhite' => 'paperwhite',
            'origin-bold' => 'origin-bold',
        ];
    }
};
