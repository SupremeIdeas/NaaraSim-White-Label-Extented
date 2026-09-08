<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §2.6: one-time video-watch claim — a user earns from a brand's video
 * once, ever, not on every replay. Same one-time shape as social_follow_claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_video_watch_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('brand_partner_videos')->cascadeOnDelete();
            $table->timestamp('watched_at');
            $table->timestamps();

            $table->unique(['user_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_video_watch_claims');
    }
};
