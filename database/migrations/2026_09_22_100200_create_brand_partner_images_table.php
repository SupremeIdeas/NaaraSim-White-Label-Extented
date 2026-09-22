<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand Profile page (owner request, 2026-09-22): a small featured-image
 * gallery per brand, separate from the single card-listing hero/fallback
 * image on brand_partners itself, so a profile can show more than one shot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_partner_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->string('image_path', 512);
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['brand_partner_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_partner_images');
    }
};
