<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §2.1: extend the BUILD-6 brand_partners table into a self-service
 * listing (owner, category, lifecycle status, featured flag, hero art, priority
 * score, current plan) WITHOUT a second brand table. owner_user_id NULL = the
 * original admin-placed/featured path; set = a self-service business listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_partners', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('category')->nullable()->after('short_description');
            // pending_setup | active | paused_billing | disabled
            $table->string('listing_status', 24)->default('active')->after('category');
            $table->boolean('is_featured')->default(false)->after('listing_status');
            $table->string('hero_image_path', 512)->nullable()->after('fallback_image');
            $table->integer('priority_score')->default(0)->after('is_featured');
            $table->foreignId('current_plan_id')->nullable()->after('priority_score')->constrained('brand_subscription_plans')->nullOnDelete();

            $table->index(['listing_status', 'category']);
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('current_plan_id');
            $table->dropColumn(['category', 'listing_status', 'is_featured', 'hero_image_path', 'priority_score']);
        });
    }
};
