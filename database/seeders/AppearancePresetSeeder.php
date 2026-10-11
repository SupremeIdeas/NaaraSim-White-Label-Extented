<?php

namespace Database\Seeders;

use App\Models\AppearancePreset;
use Illuminate\Database\Seeder;

/**
 * Seeds every skin and accent from config/appearance.php. Idempotent: re-running never overwrites what an admin has
 * changed (enabled / default / access flags) on an existing row. A skin whose stylesheet has not shipped
 * (config 'built') is seeded DISABLED, and so is a built Pro skin until an admin switches it on.
 */
class AppearancePresetSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('appearance');
        $built = (array) $cfg['built'];

        foreach ($cfg['skins'] as $key => $s) {
            $row = AppearancePreset::firstOrNew(['kind' => AppearancePreset::SKIN, 'key' => $key]);
            if (! $row->exists) {
                $row->fill([
                    'label' => $s['label'], 'sort' => $s['sort'], 'enabled' => in_array($key, $built, true) && $s['access'] === 'free',
                    'is_default' => $key === $cfg['default_skin'], 'access' => $s['access'], 'min_plan_tier' => $s['min_plan_tier'],
                ])->save();
            }
        }
        $i = 0;
        foreach ($cfg['accents'] as $key => $a) {
            $row = AppearancePreset::firstOrNew(['kind' => AppearancePreset::ACCENT, 'key' => $key]);
            if (! $row->exists) {
                $row->fill(['label' => $a['label'], 'sort' => ++$i, 'enabled' => true, 'is_default' => $key === $cfg['default_accent'], 'access' => $a['access']])->save();
            }
        }
    }
}
