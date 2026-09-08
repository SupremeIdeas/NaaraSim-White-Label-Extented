<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Theme visual rebuild, Batch 2 (owner request, 2026-09-07): "start the
 * next batch 5 to make it 10". Two workstreams in one migration, additive
 * only (never overwrites an admin-assigned value):
 *   - aries-contrast, paperwhite, origin-bold (batch 1's remaining 3)
 *     complete their full suite: login_bg, landing_hero, about_page,
 *     how_it_works_page, contact_page, footer.
 *   - solar-flare, noir-reserve are brand new: header, bottom_nav, login,
 *     login_bg, landing_hero, about_page, how_it_works_page, contact_page,
 *     footer — the complete set, since neither had any section style
 *     assigned before this migration.
 * Matches ThemePresetSeeder::batch1SectionStyles() exactly so a fresh
 * install and an upgraded database always agree.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->assignments() as $slug => $sections) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            foreach ($sections as $key => $value) {
                if (! array_key_exists($key, $current)) {
                    $current[$key] = $value;
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
            foreach ($sections as $key => $value) {
                if (($current[$key] ?? null) === $value) {
                    unset($current[$key]);
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
        $fullSuite = fn (string $slug, string $loginBg) => [
            'login_bg' => $loginBg,
            'landing_hero' => $slug,
            'about_page' => $slug,
            'how_it_works_page' => $slug,
            'contact_page' => $slug,
            'footer' => $slug,
        ];

        return [
            'aries-contrast' => $fullSuite('aries-contrast', 'dot-grid'),
            'paperwhite' => $fullSuite('paperwhite', 'none'),
            'origin-bold' => $fullSuite('origin-bold', 'dot-grid'),
            'solar-flare' => array_merge([
                'header' => 'solar-flare',
                'bottom_nav' => 'solar-flare',
                'login' => 'solar-flare',
            ], $fullSuite('solar-flare', 'dot-grid')),
            'noir-reserve' => array_merge([
                'header' => 'noir-reserve',
                'bottom_nav' => 'noir-reserve',
                'login' => 'noir-reserve',
            ], $fullSuite('noir-reserve', 'mesh-grain')),
        ];
    }
};
