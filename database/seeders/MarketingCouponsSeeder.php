<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Support\MarketingCoupons;
use Illuminate\Database\Seeder;

/**
 * Seeds the two evergreen marketing coupons the dashboard nudges surface
 * (owner request). Idempotent — safe to re-run. The nudges themselves are off
 * until the admin flips MarketingCoupons::FLAG; the discounts are still
 * MarginGuard-clamped at checkout regardless.
 */
class MarketingCouponsSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(
            ['code' => MarketingCoupons::WELCOME_CODE],
            [
                'percent_off' => 10.00,
                'applies_to' => 'all',
                'per_user_limit' => 1,
                'is_active' => true,
            ],
        );

        Coupon::updateOrCreate(
            ['code' => MarketingCoupons::COMEBACK_CODE],
            [
                'percent_off' => 15.00,
                'applies_to' => 'all',
                'per_user_limit' => 1,
                'is_active' => true,
            ],
        );
    }
}
