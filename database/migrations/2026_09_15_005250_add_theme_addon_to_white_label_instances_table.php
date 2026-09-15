<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (2026-09-15) — an optional "custom theme request" add-on
 * offered at Merchant V2 white-label license purchase time (App\Support\
 * ThemeAddonCatalog: none/basic/elegant/premium). theme_addon_price_usd is a
 * SNAPSHOT of the catalog price at request time, so a later admin price
 * change never retroactively changes what an already-requested instance
 * owes — the same "never a stale/typed figure, but never silently
 * recomputed after the fact either" posture as price_usd itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->string('theme_addon', 20)->nullable()->after('price_usd');
            $table->decimal('theme_addon_price_usd', 10, 2)->nullable()->after('theme_addon');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn(['theme_addon', 'theme_addon_price_usd']);
        });
    }
};
