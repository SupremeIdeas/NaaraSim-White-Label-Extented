<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motion banners: a banner may carry an optional short video (mp4/webm) that
 * plays muted-looping in its zone (e.g. the "More" menu). image_url stays as the
 * poster / fallback for reduced-motion and slow connections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('image_url_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
