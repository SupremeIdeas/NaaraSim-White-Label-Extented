<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full per-theme page suite (owner request, 2026-09-07): "for each theme...
 * will and must carry its own homepage, about us page, and 3 extra
 * important page layouts." `landing_content` (shipped earlier) already
 * covers the homepage; this column covers the rest (about/how-it-works/
 * contact), nested by page key since a theme could in principle carry a
 * different content set per page: `{about_page: {...}, how_it_works_page:
 * {...}, contact_page: {...}}`. Nullable, defaults to nothing, so every
 * theme keeps its existing page content until an admin assigns a custom
 * page style and edits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->json('page_content')->nullable()->after('landing_content');
        });
    }

    public function down(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->dropColumn('page_content');
        });
    }
};
