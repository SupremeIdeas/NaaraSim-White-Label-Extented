<?php

namespace Database\Seeders;

use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use Illuminate\Database\Seeder;

/**
 * Prompt 21-EXT §1 — the seeded four-tier catalog, at the exact prices the
 * owner specified. Each plan's cover_image_url points to a committed WebP
 * banner under `public/images/white-label/` (owner-supplied artwork,
 * converted/optimized to the same convention as `public/images/audiences/`)
 * — the plan-management admin screen (§1.4) can still override it per plan.
 */
class WhiteLabelLicensePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => WhiteLabelLicensePlan::KEY_BASIC,
                'name' => 'Basic',
                'tagline' => 'Get started with your own white-label platform',
                'description' => 'The entry point into your own NaaraSim white label. Core connectivity features are live immediately; the richest features unlock automatically once you complete the balance to Extended, whenever you\'re ready to scale.',
                'price_usd' => 1500.00,
                'tier' => WhiteLabelInstance::TIER_NORMAL,
                'support_level' => WhiteLabelLicensePlan::SUPPORT_STANDARD,
                'cover_image_url' => asset('images/white-label/plan-basic.webp'),
                'features' => [
                    'Your own branded white-label platform',
                    'Core eSIM + Numbers connectivity',
                    'Standard support',
                    'Upgrade to Extended anytime by paying the remaining balance',
                ],
                'sort_order' => 1,
            ],
            [
                'key' => WhiteLabelLicensePlan::KEY_MEDIUM,
                'name' => 'Medium',
                'tagline' => 'A head start toward the full platform',
                'description' => 'The same core platform as Basic, with a smaller balance left to complete before your license automatically extends to full capability.',
                'price_usd' => 2500.00,
                'tier' => WhiteLabelInstance::TIER_NORMAL,
                'support_level' => WhiteLabelLicensePlan::SUPPORT_STANDARD,
                'cover_image_url' => asset('images/white-label/plan-medium.webp'),
                'features' => [
                    'Your own branded white-label platform',
                    'Core eSIM + Numbers connectivity',
                    'Standard support',
                    'Smallest remaining balance to reach Extended',
                ],
                'sort_order' => 2,
            ],
            [
                'key' => WhiteLabelLicensePlan::KEY_EXTENDED,
                'name' => 'Extended',
                'tagline' => 'The full platform, fully unlocked, day one',
                'description' => 'Every feature unlocked immediately — no balance, no waiting. Full entitlement across the entire NaaraSim platform from the moment your license is issued.',
                'price_usd' => 5000.00,
                'tier' => WhiteLabelInstance::TIER_EXTENDED,
                'support_level' => WhiteLabelLicensePlan::SUPPORT_STANDARD,
                'cover_image_url' => asset('images/white-label/plan-extended.webp'),
                'features' => [
                    'Your own branded white-label platform',
                    'Full platform capability, unlocked immediately',
                    'Standard support',
                ],
                'sort_order' => 3,
            ],
            [
                'key' => WhiteLabelLicensePlan::KEY_EXTENDED_V2,
                'name' => 'Extended V2',
                'tagline' => 'Extended, with priority support from Supreme Ideas Agency',
                'description' => 'Identical full-platform capability to Extended, with priority support directly from the Supreme Ideas Agency team for merchants who want extra hands-on help scaling.',
                'price_usd' => 7500.00,
                'tier' => WhiteLabelInstance::TIER_EXTENDED,
                'support_level' => WhiteLabelLicensePlan::SUPPORT_PRIORITY,
                'cover_image_url' => asset('images/white-label/plan-extended-v2.webp'),
                'features' => [
                    'Your own branded white-label platform',
                    'Full platform capability, unlocked immediately',
                    'Priority support from Supreme Ideas Agency',
                ],
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            WhiteLabelLicensePlan::updateOrCreate(['key' => $plan['key']], $plan + ['is_active' => true]);
        }
    }
}
