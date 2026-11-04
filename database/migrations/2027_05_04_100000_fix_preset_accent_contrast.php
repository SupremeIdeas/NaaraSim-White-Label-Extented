<?php

use App\Models\ThemePreset;
use Illuminate\Database\Migrations\Migration;

/** Three presets had a raw accent too dark to read on their own dark surface (1.0 to 2.9:1). Lift each just enough (3:1 or better); only if the admin has not changed it. */
return new class extends Migration
{
    public function up(): void
    {
        $fixes = ['mango-burst' => ['124 58 140', '140 82 154'], 'origin-bold' => ['23 23 23', '107 107 107'], 'copper-line' => ['47 102 144', '55 108 148']];
        try {
            foreach ($fixes as $slug => [$old, $new]) {
                $row = ThemePreset::where('slug', $slug)->first();
                if (! $row) {
                    continue;
                }
                $tokens = (array) $row->tokens;
                if (($tokens['colors']['accent'] ?? null) === $old) {
                    $tokens['colors']['accent'] = $new;
                    $row->forceFill(['tokens' => $tokens])->save();
                }
            }
            \App\Support\ThemePreset::bust();
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
    }
};
