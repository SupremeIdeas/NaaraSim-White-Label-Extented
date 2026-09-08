<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NAARA THEME SYSTEM — a switchable visual skin (row in theme_presets). This
 * model backs the seeder and the admin picker (Batch 2); runtime rendering goes
 * through App\Support\ThemePreset (cached, validated), NOT this model, so the
 * read path never queries per request.
 */
class ThemePreset extends Model
{
    protected $fillable = [
        'slug', 'name', 'persona', 'tokens', 'color_overrides', 'header_settings', 'icon_family',
        'hero_assets', 'layout_variants', 'section_styles', 'landing_content', 'page_content', 'is_built_in', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'color_overrides' => 'array',
            'header_settings' => 'array',
            'icon_family' => 'array',
            'hero_assets' => 'array',
            'layout_variants' => 'array',
            'section_styles' => 'array',
            'landing_content' => 'array',
            'page_content' => 'array',
            'is_built_in' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
