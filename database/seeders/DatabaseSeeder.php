<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,          // must run before DefaultAdminSeeder
            PricingSettingsSeeder::class,
            DefaultAdminSeeder::class,  // seeds the default super_admin
            DefaultBannerSeeder::class, // ships the five brand promo banners
            MarketingCouponsSeeder::class, // welcome + comeback marketing coupons
            EsimCompatibleDeviceSeeder::class, // eSIM device compatibility catalogue
            EsimImageSeeder::class,     // eSIM country/region navigation imagery (BUILD-8)
            NumbersBentoSeeder::class,  // six Numbers landing bento cards
            BrandPlanSeeder::class,     // brand-directory subscription starter plans (BUILD-9)
            BrandPartnerSeeder::class,  // Brand Hunt directory demo brands + handles/images (owner request, 2026-09-22)
            ProviderRegistrySeeder::class, // provider registry metadata + URLs (BUILD-14)
            ProviderExpansionSeeder::class, // new adapters, enabled=false (BUILD-18)
            ThemePresetSeeder::class,       // switchable visual skins — built-in naara-official (Theme Batch 1)
        ]);
    }
}
