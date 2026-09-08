<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_07_162000_assign_theme_footer_styles data migration —
 * assigns neon-vertex and midnight-signal's 'footer' section style.
 * Additive only: never overwrites an admin-assigned value, and down()
 * removes only the exact value it assigned.
 */
class ThemeFooterStylesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_162000_assign_theme_footer_styles.php');
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

    public function test_up_assigns_the_footer_style(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertSame('neon-vertex', $sections['footer']);
        $this->assertSame('neon-vertex', $sections['header']);
    }

    public function test_up_never_overwrites_an_admin_assigned_style(): void
    {
        $this->insertMinimalRow('midnight-signal', ['footer' => 'default']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'midnight-signal')->value('section_styles'), true);
        $this->assertSame('default', $sections['footer']);
    }

    public function test_up_skips_an_unknown_slug_without_error(): void
    {
        // Neither target slug has a row in this test's minimal dataset —
        // up() must no-op cleanly rather than error.
        $this->insertMinimalRow('some-other-theme', ['header' => 'default']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'some-other-theme')->value('section_styles'), true);
        $this->assertArrayNotHasKey('footer', $sections);
    }

    public function test_down_removes_only_the_value_it_assigned(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertArrayNotHasKey('footer', $sections);
        $this->assertSame('neon-vertex', $sections['header']);
    }

    public function test_down_leaves_an_admin_overridden_value_untouched(): void
    {
        $this->insertMinimalRow('midnight-signal', []);
        $migration = $this->migration();
        $migration->up();

        // Admin later changes it away from the migration's own value.
        DB::table('theme_presets')->where('slug', 'midnight-signal')->update([
            'section_styles' => json_encode(['footer' => 'default']),
        ]);

        $migration->down();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'midnight-signal')->value('section_styles'), true);
        $this->assertSame('default', $sections['footer']);
    }
}
