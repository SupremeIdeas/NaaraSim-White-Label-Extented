<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §2.4: premium-tier video previews. The current plan's
 * video_previews_allowed is a hard cap; a downgrade never leaves more videos
 * live than the new plan allows (§5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_partner_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->string('video_url', 512);
            $table->string('platform', 16); // youtube | vimeo
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['brand_partner_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_partner_videos');
    }
};
