<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 5 #11 Phase A1 — two selectable announcement presentation styles.
 * `banner_hero` uses `image_path` (dashboard-hero dimensions, 1600x800 2:1);
 * `dark_feature` uses `feature_image_path` (a smaller inset preview) plus
 * `bullets` and an optional lighter-weight secondary link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('style')->default('banner_hero')->after('icon');
            $table->string('image_path')->nullable()->after('style');
            $table->string('feature_image_path')->nullable()->after('image_path');
            $table->json('bullets')->nullable()->after('feature_image_path');
            $table->string('secondary_label')->nullable()->after('cta_url');
            $table->string('secondary_url')->nullable()->after('secondary_label');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['style', 'image_path', 'feature_image_path', 'bullets', 'secondary_label', 'secondary_url']);
        });
    }
};
