<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-theme landing pages (owner request, 2026-09-07): "those pages I
 * uploaded... were supposed to be a unique preset style independent to
 * carry their own perfect landing page and hero unique style for each of
 * those themes... the existing themes were supposed to be able to help
 * admin change text images and the rest for that theme." This is the
 * content store behind that: whichever fields a theme's assigned
 * `landing_hero` style declares (see App\Support\LandingHeroLibrary) get
 * their admin-edited values stored here, per theme. Nullable, defaults to
 * nothing, so every theme keeps rendering the existing site-wide homepage
 * content (SiteContent/PageBuilder) until an admin actually applies a
 * custom landing style to that theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->json('landing_content')->nullable()->after('section_styles');
        });
    }

    public function down(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->dropColumn('landing_content');
        });
    }
};
