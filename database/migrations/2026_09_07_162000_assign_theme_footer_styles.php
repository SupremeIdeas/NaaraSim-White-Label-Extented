<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Swappable FOOTER section (owner request, 2026-09-07: "please all themes
 * too should have unique footer too... not always we get a straight line
 * footer, then footer swappable too"). neon-vertex and midnight-signal are
 * the first two themes with a hand-built footer — assigns their 'footer'
 * section style. Additive only, same discipline as every other batch-1
 * backfill in this series (never overwrites an admin-assigned value).
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
            if (! array_key_exists('footer', $current)) {
                $current['footer'] = $style;
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
            if (($current['footer'] ?? null) === $style) {
                unset($current['footer']);
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
            'neon-vertex' => 'neon-vertex',
            'midnight-signal' => 'midnight-signal',
        ];
    }
};
