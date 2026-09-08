<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_06_180000_add_theme_preset_accent_dark_token data migration.
 * Like the palette-refresh migration, RefreshDatabase already runs this
 * once against a table the SAME migration batch just seeded (via
 * ThemePresetSeeder, which now writes accent_dark itself) — so up()'s own
 * "already has one" no-op path is what actually gets exercised by default.
 * These tests simulate an upgrade from a database that was seeded BEFORE
 * this token existed, by stripping accent_dark back out first.
 */
class ThemePresetAccentDarkMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_06_180000_add_theme_preset_accent_dark_token.php');
    }

    /** Simulates a database that ran the OLD seeder, before accent_dark existed. */
    private function insertNaaraOfficial(array $colors): void
    {
        DB::table('theme_presets')->insert([
            'slug' => 'naara-official',
            'name' => 'Naara Official',
            'persona' => 'Current shipped look.',
            'tokens' => json_encode(['colors' => $colors]),
            'icon_family' => json_encode(['style' => '3d', 'set' => 'default']),
            'hero_assets' => json_encode([]),
            'layout_variants' => json_encode([]),
            'is_built_in' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_up_backfills_accent_dark_for_a_pre_existing_row(): void
    {
        $this->insertNaaraOfficial(['primary' => '10 110 110', 'accent' => '212 160 23', 'navy' => '13 27 42']);

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'naara-official')->first();
        $tokens = json_decode($row->tokens, true);
        $this->assertSame('148 112 16', $tokens['colors']['accent_dark']);
        // Untouched fields survive the merge.
        $this->assertSame('212 160 23', $tokens['colors']['accent']);
    }

    public function test_up_never_overwrites_an_existing_accent_dark(): void
    {
        $this->insertNaaraOfficial(['accent' => '212 160 23', 'accent_dark' => '1 2 3']);

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'naara-official')->first();
        $tokens = json_decode($row->tokens, true);
        $this->assertSame('1 2 3', $tokens['colors']['accent_dark']);
    }

    public function test_down_removes_the_backfilled_key(): void
    {
        $this->insertNaaraOfficial(['accent' => '212 160 23']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $row = DB::table('theme_presets')->where('slug', 'naara-official')->first();
        $tokens = json_decode($row->tokens, true);
        $this->assertArrayNotHasKey('accent_dark', $tokens['colors']);
    }
}
