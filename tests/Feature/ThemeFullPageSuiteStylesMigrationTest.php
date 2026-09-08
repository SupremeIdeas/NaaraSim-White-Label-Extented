<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_07_161000_assign_theme_full_page_suite_styles data
 * migration — assigns the first two themes' about_page/
 * how_it_works_page/contact_page section styles.
 */
class ThemeFullPageSuiteStylesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_161000_assign_theme_full_page_suite_styles.php');
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

    public function test_up_assigns_all_three_page_styles(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertSame('neon-vertex', $sections['about_page']);
        $this->assertSame('neon-vertex', $sections['how_it_works_page']);
        $this->assertSame('neon-vertex', $sections['contact_page']);
        $this->assertSame('neon-vertex', $sections['header']);
    }

    public function test_up_never_overwrites_an_admin_assigned_style(): void
    {
        $this->insertMinimalRow('midnight-signal', ['about_page' => 'default']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'midnight-signal')->value('section_styles'), true);
        $this->assertSame('default', $sections['about_page']);
        $this->assertSame('midnight-signal', $sections['how_it_works_page']);
    }

    public function test_down_removes_only_the_values_it_assigned(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertArrayNotHasKey('about_page', $sections);
        $this->assertArrayNotHasKey('how_it_works_page', $sections);
        $this->assertArrayNotHasKey('contact_page', $sections);
        $this->assertSame('neon-vertex', $sections['header']);
    }
}
