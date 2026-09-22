<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand Profile page (owner request, 2026-09-22): each handle can show a
 * "last post" teaser — a thumbnail + caption + the post's own URL — so a
 * visitor sees what the brand is actually posting before following out to
 * the real platform. Admin-curated, same as the existing handle/video
 * fields; not a live social API pull (none of the platforms' APIs are
 * wired up, and building OAuth + review for four networks is its own
 * project, not a field on this table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partner_handles', function (Blueprint $table) {
            $table->string('last_post_image_path', 512)->nullable()->after('verification');
            $table->string('last_post_caption', 500)->nullable()->after('last_post_image_path');
            $table->string('last_post_url', 512)->nullable()->after('last_post_caption');
            $table->timestamp('last_post_at')->nullable()->after('last_post_url');
        });
    }

    public function down(): void
    {
        Schema::table('brand_partner_handles', function (Blueprint $table) {
            $table->dropColumn(['last_post_image_path', 'last_post_caption', 'last_post_url', 'last_post_at']);
        });
    }
};
