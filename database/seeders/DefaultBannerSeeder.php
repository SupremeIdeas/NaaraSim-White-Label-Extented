<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

/**
 * Ships the five brand promo banners that come with NaaraSim out of the box
 * (Onitsha brand set, Apple-inspired glassmorphism). The artwork lives in
 * public/banners and is committed with the app, exactly like the default logos;
 * this seeder just points active Banner rows at those files so a fresh install
 * shows a populated dashboard carousel, "More" menu, and account promo without
 * the operator having to upload anything.
 *
 * Idempotent: keyed on placement + title, so re-running never duplicates. An
 * admin can still edit, reorder, deactivate, or replace any of them.
 */
class DefaultBannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            // Dashboard home carousel (3:1) — the three hero promos.
            ['dashboard_home', 'Global eSIM data', '/banners/dashboard-esim.png', '/catalogue', 1],
            ['dashboard_home', 'Virtual numbers', '/banners/dashboard-number.png', '/numbers', 2],
            ['dashboard_home', 'Global data sale', '/banners/dashboard-sale.png', '/catalogue', 3],
            // Mobile "More" menu sheet (2:1).
            ['menu_sheet', 'Refer & earn', '/banners/menu-refer-earn.png', '/referrals', 1],
            // Account / profile page (2:1).
            ['account', 'NaaraCredits rewards', '/banners/account-rewards.png', '/rewards', 1],
        ];

        foreach ($banners as [$placement, $title, $image, $link, $order]) {
            Banner::updateOrCreate(
                ['placement' => $placement, 'title' => $title],
                [
                    'image_url' => $image,
                    'image_url_mobile' => $image, // same art scales/crops per zone
                    'link_url' => $link,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }
}
