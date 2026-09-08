<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-8 §2.1 — region/country categorisation on esim_plans.
 *
 * Both columns are nullable and backward-compatible: existing rows keep working
 * with coverage_type null until the next catalogue sync derives it from each
 * provider's real signal (Airalo's slug/type; others from the countries count).
 *
 *  - region_slug   nullable string (e.g. europe, caribbean, world) — null for a
 *                  genuine single-country local plan, or when a provider gives no
 *                  real region name (never guessed).
 *  - coverage_type local | regional | global — the axis the customer-facing
 *                  Local/Regional/Global tabs (§3) filter on.
 *
 * Indexed because §3's navigation filters strictly on coverage_type (+ region_slug
 * for the Regional sub-region tiles) on every browse, all from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            // Nullable string rather than a DB enum: SQLite (tests) has no native
            // enum and coverage_type is derived/validated in the sync layer, so a
            // plain indexed string stays portable across MySQL and SQLite.
            $table->string('coverage_type')->nullable()->after('type');
            $table->string('region_slug')->nullable()->after('coverage_type');

            $table->index('coverage_type');
            $table->index('region_slug');
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->dropIndex(['coverage_type']);
            $table->dropIndex(['region_slug']);
            $table->dropColumn(['coverage_type', 'region_slug']);
        });
    }
};
