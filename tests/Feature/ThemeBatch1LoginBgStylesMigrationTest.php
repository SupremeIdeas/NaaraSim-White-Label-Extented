<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_07_130000_assign_theme_batch1_login_bg_styles data migration
 * — the decorative login_bg follow-up to the batch-1 structural assignment.
 */
class ThemeBatch1LoginBgStylesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_130000_assign_theme_batch1_login_bg_styles.php');
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

    public function test_up_assigns_the_persona_specific_effect(): void
    {
        $this->insertMinimalRow('neon-vertex', ['header' => 'neon-vertex']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'neon-vertex')->value('section_styles'), true);
        $this->assertSame('aurora', $sections['login_bg']);
        $this->assertSame('neon-vertex', $sections['header']);
    }

    public function test_up_assigns_none_for_paperwhite(): void
    {
        $this->insertMinimalRow('paperwhite');

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'paperwhite')->value('section_styles'), true);
        $this->assertSame('none', $sections['login_bg']);
    }

    public function test_up_never_overwrites_an_admin_assigned_effect(): void
    {
        $this->insertMinimalRow('origin-bold', ['login_bg' => 'none']);

        $this->migration()->up();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'origin-bold')->value('section_styles'), true);
        $this->assertSame('none', $sections['login_bg']);
    }

    public function test_down_removes_only_the_login_bg_value_it_assigned(): void
    {
        $this->insertMinimalRow('midnight-signal', ['header' => 'midnight-signal']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $sections = json_decode(DB::table('theme_presets')->where('slug', 'midnight-signal')->value('section_styles'), true);
        $this->assertArrayNotHasKey('login_bg', $sections);
        $this->assertSame('midnight-signal', $sections['header']);
    }
}
