<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_07_150000_assign_theme_landing_hero_styles data migration —
 * assigns the first two per-theme custom landing pages.
 */
class ThemeLandingHeroStylesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_150000_assign_theme_landing_hero_styles.php');
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

    public function test_up_assigns_the_landing_hero_style(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertSame('neon-vertex', $sections['landing_hero']);
        $this->assertSame('neon-vertex', $sections['header']);
    }

    public function test_up_never_overwrites_an_admin_assigned_style(): void
    {
        $this->insertMinimalRow('midnight-signal', ['landing_hero' => 'default']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'midnight-signal')->value('section_styles'), true);
        $this->assertSame('default', $sections['landing_hero']);
    }

    public function test_down_removes_only_the_landing_hero_value_it_assigned(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertArrayNotHasKey('landing_hero', $sections);
        $this->assertSame('neon-vertex', $sections['header']);
    }
}
