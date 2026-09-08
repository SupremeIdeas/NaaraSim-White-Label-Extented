<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §2.2: admin-configurable brand subscription pricing tiers. The
 * follower guarantee per handle/month is the transparency mechanism §6 is built
 * around. Seeded with real starter plans (BrandPlanSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_usd_per_month', 12, 2);
            $table->unsignedInteger('handles_included')->default(1);
            $table->unsignedInteger('guaranteed_followers_per_handle_per_month')->default(0);
            $table->unsignedTinyInteger('video_previews_allowed')->default(0); // 0,1,2
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_subscription_plans');
    }
};
