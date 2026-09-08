<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage video section entries (BUILD-3 §8). Each entry is a title + a video
 * (a self-hosted upload URL OR a YouTube id — admin's choice), a poster image,
 * and an orientation. Ordered + toggleable by the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_type', 16)->default('youtube'); // youtube | upload
            $table->string('youtube_id')->nullable();
            $table->string('video_url')->nullable();  // self-hosted (Wasabi/R2/local)
            $table->string('poster_url')->nullable();
            $table->string('orientation', 16)->default('portrait'); // portrait | landscape
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_videos');
    }
};
