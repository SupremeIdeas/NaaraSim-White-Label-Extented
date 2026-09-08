<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Theme visual rebuild, Batch 1 of 8 (owner request, 2026-09-07): the first
 * 5 presets to get a genuinely unique header + login screen instead of the
 * shared "default" chrome — aries-contrast, midnight-signal, neon-vertex,
 * paperwhite, origin-bold. Each points 'header' and 'login' at a style key
 * matching its own slug (see ThemePreset::SECTION_STYLE_ALLOW and the new
 * partials under resources/views/components/theme-sections/header/ and
 * resources/views/components/layouts/theme-sections/login/).
 *
 * Purely additive, same discipline as the accent_dark backfill: only sets a
 * section key that is currently absent, so an admin who has already tuned
 * section_styles on one of these rows (once the picker UI exists) is never
 * overwritten, and re-running this migration is always a no-op after the
 * first successful run.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->assignments() as $slug => $sections) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue; // never seeded on this install
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            foreach ($sections as $section => $style) {
                if (! array_key_exists($section, $current)) {
                    $current[$section] = $style;
                }
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->assignments() as $slug => $sections) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            foreach ($sections as $section => $style) {
                if (($current[$section] ?? null) === $style) {
                    unset($current[$section]);
                }
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => $current === [] ? null : json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, array<string, string>> */
    private function assignments(): array
    {
        return [
            'aries-contrast' => ['header' => 'aries-contrast', 'login' => 'aries-contrast'],
            'midnight-signal' => ['header' => 'midnight-signal', 'login' => 'midnight-signal'],
            'neon-vertex' => ['header' => 'neon-vertex', 'login' => 'neon-vertex'],
            'paperwhite' => ['header' => 'paperwhite', 'login' => 'paperwhite'],
            'origin-bold' => ['header' => 'origin-bold', 'login' => 'origin-bold'],
        ];
    }
};
