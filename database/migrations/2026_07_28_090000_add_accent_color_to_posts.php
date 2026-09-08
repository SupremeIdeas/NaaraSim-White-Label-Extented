<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-post accent colour (Blog overhaul §5). Drives the scroll-tied background
 * transition as the reader moves from one post into the next. Nullable — with a
 * graceful category/base fallback in the model, so an admin who never sets it
 * still gets a coherent result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('accent_color', 7)->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
