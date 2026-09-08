<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin colour override (owner request, 2026-09-07): "admin Also change
 * color pallet any any theme he picks... advance settings to change each
 * color with color code and save to override each color then with a
 * reset to default color." Stored SEPARATELY from `tokens` (the seeded
 * base palette) so an override never destroys the original default —
 * "reset to default" is just removing that colour's key from this column,
 * exactly the same additive-override discipline as landing_content/
 * page_content earlier this session. Nullable, defaults to nothing, so
 * every existing theme renders identically until an admin overrides a
 * colour on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->json('color_overrides')->nullable()->after('tokens');
        });
    }

    public function down(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->dropColumn('color_overrides');
        });
    }
};
