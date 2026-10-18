<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One stored logo per BRAND (not per product): the same brand from two providers shares it. Written by the Logo Wizard.
        Schema::create('gift_card_brand_logos', function (Blueprint $table) {
            $table->id();
            $table->string('brand_key')->unique();
            $table->string('brand_name');
            $table->string('path')->nullable();          // stored file on the media disk (webp, or svg for vector sources and monograms)
            $table->string('source', 20)->default('none'); // provider | simpleicons | site | sibling | upload | monogram
            $table->string('source_url', 500)->nullable();
            $table->string('domain')->nullable();         // official domain used for the site lookup
            $table->string('sha256', 64)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable(); // 0-100 for a Claude-resolved domain
            $table->string('status', 12)->default('review'); // approved | review | fallback
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_brand_logos');
    }
};
