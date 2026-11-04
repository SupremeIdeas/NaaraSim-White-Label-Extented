<?php

use App\Models\Setting;
use App\Models\ThemePreset;
use Illuminate\Database\Migrations\Migration;

/**
 * White-label default look: core black, white, graphite and silver slate instead of orchid and lime. The previous colourway stays as the
 * "orchid-lime" preset. An install whose admin already chose its own brand colours (any brand.color_* setting) is left exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $custom = false;
            foreach (['primary', 'accent', 'navy', 'action'] as $k) {
                if ((string) Setting::getValue('brand.color_'.$k, '') !== '') {
                    $custom = true;
                }
            }

            $orchid = ['primary' => '162 28 175', 'primary_dark' => '134 25 143', 'accent' => '132 204 22', 'accent_dark' => '77 124 15', 'navy' => '13 27 42', 'action' => '232 65 42'];
            if (! ThemePreset::where('slug', 'orchid-lime')->exists()) {
                ThemePreset::create([
                    'slug' => 'orchid-lime', 'name' => 'Orchid Lime',
                    'persona' => 'The original colourway of this platform: orchid magenta, fresh lime, midnight navy. Saved so it is one click away.',
                    'tokens' => ['colors' => $orchid, 'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'], 'typography' => ['display' => 'Supreme Display', 'sans' => 'Didact Gothic'], 'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],
                    'icon_family' => ['style' => '3d', 'set' => 'default'], 'hero_assets' => ['dashboard' => '/img/themes/balloons.webp'],
                    'is_built_in' => false, 'sort_order' => 1,
                ]);
            }

            $row = ThemePreset::where('slug', 'naara-official')->first();
            if ($row && ! $custom && (($row->tokens['colors']['primary'] ?? '') === '162 28 175')) {
                $tokens = (array) $row->tokens;
                $tokens['colors'] = array_merge((array) ($tokens['colors'] ?? []), ['primary' => '35 39 45', 'primary_dark' => '16 18 21', 'accent' => '148 160 176', 'accent_dark' => '71 80 94', 'navy' => '10 11 13', 'action' => '61 90 254']);
                $row->forceFill(['name' => 'Graphite', 'persona' => 'The shipped default of this platform — core black and white with graphite and silver slate, 3D icons. Permanent default. The orchid and lime colourway is kept as the Orchid Lime preset.', 'tokens' => $tokens])->save();
            }
            \App\Support\ThemePreset::bust();
        } catch (\Throwable) {
            // A fresh install has no rows yet; the seeder writes the new defaults.
        }
    }

    public function down(): void
    {
    }
};
