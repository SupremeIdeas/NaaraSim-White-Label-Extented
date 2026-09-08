<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-6 §C.1.3: the "extra layer" — other brands' handles, shown on the Brand
 * Partner Hunt page. Each brand drives its own scroll-tied section background.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_partners', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name');
            $table->string('short_description', 500)->nullable();
            $table->string('fallback_image', 512)->nullable();
            $table->string('background_color', 9)->default('#0A6E6E'); // hex, drives the scroll bg
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_partners');
    }
};
