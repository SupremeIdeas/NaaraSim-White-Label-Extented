<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-manageable bento card content (Numbers V6 §2). One row per landing card
 * (verify / rent / line / call_forwarding / internet_calls / contact_management)
 * so the admin can edit copy, badge, icon/image, bullets, order and visibility
 * without a deploy. The 2/1/2/1 responsive layout is fixed in the view; these
 * rows only carry content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbers_bento_cards', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();            // one of the six card keys
            $table->string('badge_label')->nullable();  // FEATURED / POPULAR / …
            $table->string('icon_path')->nullable();
            $table->string('image_path')->nullable();
            $table->string('title');
            $table->string('subtitle', 500);
            $table->json('bullets')->nullable();        // 0–4 short lines
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbers_bento_cards');
    }
};
