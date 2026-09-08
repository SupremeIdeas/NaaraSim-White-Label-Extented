<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_04_150000_refresh_theme_preset_palettes data migration. Since
 * RefreshDatabase runs every migration against an EMPTY theme_presets table,
 * the migration's own up() (which already ran once per test via
 * RefreshDatabase) never exercises its "recolour an already-seeded row" path —
 * there's nothing to recolour yet. These tests simulate a database that
 * already ran the OLD ThemePresetSeeder by inserting an old-palette row by
 * hand, then re-invoking the migration directly.
 */
class ThemePresetPaletteRefreshMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_04_150000_refresh_theme_preset_palettes.php');
    }

    private function insertOldAuroraShift(): void
    {
        DB::table('theme_presets')->insert([
            'slug' => 'aurora-shift',
            'name' => 'Aurora Shift',
            'persona' => 'Cooler teal-to-indigo gradient hero bands, glassmorphic wallet card.',
            'tokens' => json_encode([
                'colors' => ['primary' => '30 110 140', 'primary_dark' => '20 78 100', 'accent' => '129 140 248', 'navy' => '11 22 40', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => 'Supreme Display', 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
            ]),
            'icon_family' => json_encode(['style' => 'sprite', 'set' => 'naara-sprite-01']),
            'hero_assets' => json_encode(['dashboard' => '/img/themes/islands-female.webp']),
            'layout_variants' => json_encode(['dashboard_home' => 'variant-a']),
            'is_built_in' => false,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_up_recolours_a_row_still_on_the_old_default_palette(): void
    {
        $this->insertOldAuroraShift();

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'aurora-shift')->first();
        $tokens = json_decode($row->tokens, true);

        $this->assertSame('Indigo Current', $row->name);
        $this->assertSame('59 63 140', $tokens['colors']['primary']);
        // Untouched fields survive the merge.
        $this->assertSame('1.5rem', $tokens['radius']['card']);
        $this->assertSame('Figtree', $tokens['typography']['sans']);
    }

    public function test_up_never_overwrites_a_row_an_admin_already_tuned(): void
    {
        $this->insertOldAuroraShift();
        DB::table('theme_presets')->where('slug', 'aurora-shift')->update([
            'tokens' => json_encode(['colors' => ['primary' => '1 2 3']]),
        ]);

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'aurora-shift')->first();
        $tokens = json_decode($row->tokens, true);
        $this->assertSame('1 2 3', $tokens['colors']['primary']);
        $this->assertSame('Aurora Shift', $row->name, 'Name is untouched too when the guard trips.');
    }

    public function test_up_inserts_the_five_new_presets_when_missing(): void
    {
        // RefreshDatabase already ran this migration once against an empty
        // table (inserting them); delete them to genuinely exercise the
        // "insert when missing" path rather than a no-op re-run.
        DB::table('theme_presets')
            ->whereIn('slug', ['verdant-pulse', 'cobalt-frost', 'mango-burst', 'arctic-teal', 'rosewood-luxe'])
            ->delete();

        $this->migration()->up();

        $slugs = DB::table('theme_presets')
            ->whereIn('slug', ['verdant-pulse', 'cobalt-frost', 'mango-burst', 'arctic-teal', 'rosewood-luxe'])
            ->pluck('slug');

        $this->assertCount(5, $slugs);
    }

    public function test_down_restores_the_old_palette_and_removes_the_new_rows(): void
    {
        $this->insertOldAuroraShift();
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $row = DB::table('theme_presets')->where('slug', 'aurora-shift')->first();
        $tokens = json_decode($row->tokens, true);
        $this->assertSame('Aurora Shift', $row->name);
        $this->assertSame('30 110 140', $tokens['colors']['primary']);

        $remaining = DB::table('theme_presets')
            ->whereIn('slug', ['verdant-pulse', 'cobalt-frost', 'mango-burst', 'arctic-teal', 'rosewood-luxe'])
            ->count();
        $this->assertSame(0, $remaining);
    }
}
