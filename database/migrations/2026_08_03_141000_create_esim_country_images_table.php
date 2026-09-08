<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-8 §2.2 — admin-managed imagery per country for the eSIM
 * navigation grid.
 *
 *  - icon_path         small grid-tile image (webp) shown on the Local grid.
 *  - detail_image_path larger banner shown at the top of a selected country's
 *                      plan list.
 *
 * Both nullable independently — a country with neither shows a clean flag/glyph
 * fallback (§2.4), never a broken image. Uploads route through MediaStorage, so
 * the stored value is a public URL (not a raw disk path), same as every other
 * admin-managed image in the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_country_images', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->unique(); // ISO2, uppercase
            $table->string('icon_path')->nullable();
            $table->string('detail_image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_country_images');
    }
};
