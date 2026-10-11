<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The skin stylesheets shipped after the preset rows were first seeded (only Surface was enabled then).
 * Switch on the free skins that now have CSS. Pro skins stay off for an admin to enable deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        $free = collect((array) config('appearance.skins'))
            ->filter(fn ($s, $key) => ($s['access'] ?? null) === 'free' && in_array($key, (array) config('appearance.built'), true))
            ->keys()->all();

        DB::table('appearance_presets')->where('kind', 'skin')->whereIn('key', $free)->update(['enabled' => true]);
    }

    public function down(): void
    {
        // Enabling is not reversible in a meaningful way: an admin may have switched skins since.
    }
};
