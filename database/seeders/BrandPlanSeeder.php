<?php

namespace Database\Seeders;

use App\Models\BrandSubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * BUILD-9 §2.2: ship real, editable starter plans so the directory isn't empty
 * at build time. Names read like a growth product, not "Tier 1/2/3". Admin can
 * edit price / handles / guarantees / video allowance immediately.
 */
class BrandPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // credit_reward_per_follow (owner request, 2026-09-22): the NaaraCredit
            // a follower earns per handle-follow scales with the brand's plan tier
            // instead of being typed flat per handle — admin-editable below.
            ['name' => 'Starter Reach', 'price_usd_per_month' => 19, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 3, 'sort_order' => 1],
            ['name' => 'Growth', 'price_usd_per_month' => 49, 'handles_included' => 3, 'guaranteed_followers_per_handle_per_month' => 150, 'video_previews_allowed' => 1, 'credit_reward_per_follow' => 5, 'sort_order' => 2],
            ['name' => 'Spotlight', 'price_usd_per_month' => 99, 'handles_included' => 5, 'guaranteed_followers_per_handle_per_month' => 350, 'video_previews_allowed' => 2, 'credit_reward_per_follow' => 8, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            BrandSubscriptionPlan::firstOrCreate(['name' => $plan['name']], $plan + ['is_active' => true]);
        }
    }
}
