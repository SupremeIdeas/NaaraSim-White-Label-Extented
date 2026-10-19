<?php

use App\Models\ThemePreset;
use Illuminate\Database\Migrations\Migration;

/** White-label default brand: the built-in theme is now Orchid Lime instead of the master's teal and gold, on installs that already seeded the old palette. */
return new class extends Migration
{
    public function up(): void
    {
        $row = ThemePreset::where('slug', 'naara-official')->first();
        if (! $row) {
            return;
        }
        $tokens = (array) $row->tokens;
        $tokens['colors'] = array_merge((array) ($tokens['colors'] ?? []), ['primary' => '162 28 175', 'primary_dark' => '134 25 143', 'accent' => '132 204 22', 'accent_dark' => '77 124 15']);
        $row->forceFill(['name' => 'Orchid Lime', 'persona' => 'The shipped default of this platform — orchid magenta, fresh lime, midnight navy, 3D icons. Permanent default.', 'tokens' => $tokens])->save();
        try {
            \App\Support\ThemePreset::bust();
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
    }
};
