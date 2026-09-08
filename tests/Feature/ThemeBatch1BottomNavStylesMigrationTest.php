<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_07_120000_assign_theme_batch1_bottom_nav_styles data
 * migration — the bottom-nav follow-up to the header/login batch-1
 * assignment, added one commit later so it needs its own additive backfill.
 */
class ThemeBatch1BottomNavStylesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_120000_assign_theme_batch1_bottom_nav_styles.php');
    }

    private function insertMinimalRow(string $slug, ?array $sectionStyles = null): void
    {
        DB::table('theme_presets')->insert([
            'slug' => $slug,
            'name' => $slug,
            'persona' => 'test',
            'tokens' => json_encode([]),
            'icon_family' => json_encode(['style' => '3d', 'set' => 'default']),
            'hero_assets' => json_encode([]),
            'layout_variants' => json_encode([]),
            'section_styles' => $sectionStyles === null ? null : json_encode($sectionStyles),
            'is_built_in' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_up_assigns_bottom_nav_for_a_pre_existing_row(): void
    {
        $this->insertMinimalRow('aries-contrast', ['header' => 'aries-contrast', 'login' => 'aries-contrast']);

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'aries-contrast')->first();
        $sections = json_decode($row->section_styles, true);
        $this->assertSame('aries-contrast', $sections['bottom_nav']);
        // Untouched keys survive the merge.
        $this->assertSame('aries-contrast', $sections['header']);
    }

    public function test_up_never_overwrites_an_admin_assigned_bottom_nav(): void
    {
        $this->insertMinimalRow('midnight-signal', ['bottom_nav' => 'default']);

        $this->migration()->up();

        $row = DB::table('theme_presets')->where('slug', 'midnight-signal')->first();
        $sections = json_decode($row->section_styles, true);
        $this->assertSame('default', $sections['bottom_nav']);
    }

    public function test_up_skips_a_slug_never_seeded_on_this_install(): void
    {
        $this->migration()->up();

        $this->assertNull(DB::table('theme_presets')->where('slug', 'neon-vertex')->first());
    }

    public function test_down_removes_only_the_bottom_nav_value_it_assigned(): void
    {
        $this->insertMinimalRow('origin-bold', ['header' => 'origin-bold']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $row = DB::table('theme_presets')->where('slug', 'origin-bold')->first();
        $sections = json_decode($row->section_styles, true);
        $this->assertArrayNotHasKey('bottom_nav', $sections);
        $this->assertSame('origin-bold', $sections['header']);
    }
}
