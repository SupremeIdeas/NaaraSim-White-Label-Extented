<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Theme visual rebuild, Batch 1 follow-up (owner request, 2026-09-07):
 * "some login bg will have custom unique dot grid material effects and
 * mesh grain on some, Aurora bg." Assigns a decorative login_bg effect
 * per persona (independent of the login STRUCTURE assigned by the earlier
 * batch-1 migrations) — additive only, same discipline as the header/login/
 * bottom_nav backfills. Paperwhite deliberately gets 'none' (its persona is
 * "zero noise"); origin-bold shares 'dot-grid' with aries-contrast,
 * demonstrating a style family reused across two themes.
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
            if (! array_key_exists('login_bg', $current)) {
                $current['login_bg'] = $style;
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
            if (($current['login_bg'] ?? null) === $style) {
                unset($current['login_bg']);
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
            'aries-contrast' => 'dot-grid',
            'midnight-signal' => 'mesh-grain',
            'neon-vertex' => 'aurora',
            'paperwhite' => 'none',
            'origin-bold' => 'dot-grid',
        ];
    }
};
