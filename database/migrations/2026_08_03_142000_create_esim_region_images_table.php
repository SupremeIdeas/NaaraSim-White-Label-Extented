<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-8 §2.3 — admin-managed imagery per region/sub-region for the
 * Regional tab's sub-region tiles (Africa, Asia, Caribbean, Europe, …) and the
 * Global tab's own tile/banner. Same shape as esim_country_images but keyed on
 * region_slug ('europe', 'world', …). Both image columns nullable, clean
 * fallback when unset (§2.4); uploads route through MediaStorage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_region_images', function (Blueprint $table) {
            $table->id();
            $table->string('region_slug')->unique();
            $table->string('icon_path')->nullable();
            $table->string('detail_image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_region_images');
    }
};
