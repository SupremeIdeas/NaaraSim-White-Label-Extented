<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Theme visual rebuild, Batch 3 (owner request, 2026-09-07): "10 to make
 * it 15". Five brand-new personas, built from scratch: header, bottom_nav,
 * login, login_bg, landing_hero, about_page, how_it_works_page,
 * contact_page, footer — the complete set, since none had any section
 * style assigned before this migration. Additive only (never overwrites an
 * admin-assigned value). Matches
 * ThemePresetSeeder::batch1SectionStyles() exactly so a fresh install and
 * an upgraded database always agree.
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
            'header' => $slug,
            'bottom_nav' => $slug,
            'login' => $slug,
            'login_bg' => $loginBg,
            'landing_hero' => $slug,
            'about_page' => $slug,
            'how_it_works_page' => $slug,
            'contact_page' => $slug,
            'footer' => $slug,
        ];

        return [
            'aurora-shift' => $fullSuite('aurora-shift', 'aurora'),
            'sunset-transit' => $fullSuite('sunset-transit', 'mesh-grain'),
            'fintra-clean' => $fullSuite('fintra-clean', 'none'),
            'capable-mono' => $fullSuite('capable-mono', 'dot-grid'),
            'waitlisty-soft' => $fullSuite('waitlisty-soft', 'aurora'),
        ];
    }
};
