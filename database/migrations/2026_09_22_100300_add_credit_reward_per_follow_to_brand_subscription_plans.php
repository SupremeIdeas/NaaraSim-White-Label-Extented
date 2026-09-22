<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (2026-09-22): the NaaraCredit a follower earns for following a
 * brand's handle should be admin-set PER PLAN TIER, not a flat number typed
 * per handle — a higher-paying brand plan can justify a richer reward.
 * Nullable: null means "no plan override, fall back to the handle's own
 * credit_reward" (SocialFollowService::rewardFor()), so existing brands
 * without a plan assigned keep behaving exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_subscription_plans', function (Blueprint $table) {
            $table->decimal('credit_reward_per_follow', 6, 2)->nullable()->after('video_previews_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('brand_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('credit_reward_per_follow');
        });
    }
};
