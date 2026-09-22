<?php

namespace Database\Seeders;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerImage;
use Illuminate\Database\Seeder;

/**
 * Brand Hunt directory demo content (owner request, 2026-09-22): real photos,
 * multiple handles per brand, and admin-curated "last post" teasers, so the
 * new Brand Profile page has something worth looking at out of the box.
 * Idempotent via updateOrCreate on brand_name — safe to re-run.
 */
class BrandPartnerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::brands() as $data) {
            $handles = $data['handles'];
            $images = $data['images'];
            unset($data['handles'], $data['images']);

            $brand = BrandPartner::updateOrCreate(['brand_name' => $data['brand_name']], $data);

            foreach ($handles as $i => $h) {
                BrandPartnerHandle::updateOrCreate(
                    ['brand_partner_id' => $brand->id, 'platform' => $h['platform']],
                    $h + ['sort_order' => $i, 'is_active' => true]
                );
            }

            foreach ($images as $i => $img) {
                // Matched on brand + position, not the path string itself —
                // matching on image_path meant a later path change (e.g. the
                // asset()-vs-root-relative fix below) created a duplicate row
                // instead of updating the existing one.
                BrandPartnerImage::updateOrCreate(
                    ['brand_partner_id' => $brand->id, 'sort_order' => $i],
                    $img
                );
            }
        }
    }

    private static function brands(): array
    {
        return [
            [
                'brand_name' => 'Sahara Airways',
                'short_description' => 'Regional flights across West and North Africa — follow us for fare drops and route launches.',
                'category' => 'Travel & Transport',
                'listing_status' => BrandPartner::STATUS_ACTIVE,
                'is_featured' => true,
                'background_color' => '#0A6E6E',
                'hero_image_path' => '/images/brand-partners/sahara-airways/hero.webp',
                'is_active' => true,
                'handles' => [
                    [
                        'platform' => 'instagram', 'handle_label' => 'Sahara Airways', 'handle_url' => 'https://instagram.com/sahara',
                        'credit_reward' => 5, 'verification' => 'self',
                        'last_post_image_path' => '/images/brand-partners/sahara-airways/gallery-1.webp',
                        'last_post_caption' => 'New night route Lagos → Accra just opened — introductory fares live this week only.',
                        'last_post_url' => 'https://instagram.com/sahara', 'last_post_at' => now()->subHours(14),
                    ],
                    [
                        'platform' => 'twitter', 'handle_label' => '@SaharaAirways', 'handle_url' => 'https://x.com/saharaairways',
                        'credit_reward' => 5, 'verification' => 'self',
                    ],
                ],
                'images' => [
                    ['image_path' => '/images/brand-partners/sahara-airways/hero.webp', 'caption' => 'Golden hour, somewhere over the Sahara.'],
                    ['image_path' => '/images/brand-partners/sahara-airways/gallery-1.webp', 'caption' => 'Our newest coach fleet for ground transfers.'],
                ],
            ],
            [
                'brand_name' => 'Lagos Eats',
                'short_description' => "Lagos' favourite food delivery app — follow for weekly deals and new restaurant drops.",
                'category' => 'Food & Drink',
                'listing_status' => BrandPartner::STATUS_ACTIVE,
                'is_featured' => false,
                'background_color' => '#D4A017',
                'hero_image_path' => '/images/brand-partners/lagos-eats/hero.webp',
                'is_active' => true,
                'handles' => [
                    [
                        'platform' => 'twitter', 'handle_label' => 'Lagos Eats', 'handle_url' => 'https://x.com/lagoseats',
                        'credit_reward' => 5, 'verification' => 'self',
                        'last_post_image_path' => '/images/brand-partners/lagos-eats/gallery-1.webp',
                        'last_post_caption' => 'This week only: free delivery on your first 3 orders from any Island restaurant.',
                        'last_post_url' => 'https://x.com/lagoseats', 'last_post_at' => now()->subDays(1),
                    ],
                    [
                        'platform' => 'instagram', 'handle_label' => '@lagoseatsng', 'handle_url' => 'https://instagram.com/lagoseatsng',
                        'credit_reward' => 5, 'verification' => 'self',
                    ],
                ],
                'images' => [
                    ['image_path' => '/images/brand-partners/lagos-eats/hero.webp', 'caption' => 'Small chops, done properly.'],
                    ['image_path' => '/images/brand-partners/lagos-eats/gallery-1.webp', 'caption' => 'Fresh bowls from our salad partners.'],
                ],
            ],
            [
                'brand_name' => 'Naija Fashion Co',
                'short_description' => 'Contemporary Nigerian streetwear and everyday essentials — new drops every Friday.',
                'category' => 'Fashion & Beauty',
                'listing_status' => BrandPartner::STATUS_ACTIVE,
                'is_featured' => false,
                'background_color' => '#8B5CF6',
                'hero_image_path' => '/images/brand-partners/naija-fashion-co/hero.webp',
                'is_active' => true,
                'handles' => [
                    [
                        'platform' => 'instagram', 'handle_label' => 'Naija Fashion Co', 'handle_url' => 'https://instagram.com/naijafashion',
                        'credit_reward' => 5, 'verification' => 'self',
                        'last_post_image_path' => '/images/brand-partners/naija-fashion-co/gallery-1.webp',
                        'last_post_caption' => "Friday drop is here — the 'Harmattan' capsule, limited to 200 pieces.",
                        'last_post_url' => 'https://instagram.com/naijafashion', 'last_post_at' => now()->subHours(3),
                    ],
                    [
                        'platform' => 'tiktok', 'handle_label' => '@naijafashionco', 'handle_url' => 'https://tiktok.com/@naijafashionco',
                        'credit_reward' => 5, 'verification' => 'self',
                    ],
                ],
                'images' => [
                    ['image_path' => '/images/brand-partners/naija-fashion-co/hero.webp', 'caption' => 'The boutique, downtown.'],
                    ['image_path' => '/images/brand-partners/naija-fashion-co/gallery-1.webp', 'caption' => 'This season\'s knitwear.'],
                ],
            ],
            [
                'brand_name' => 'Sahara Connect Shop',
                'short_description' => 'Phones, accessories and eSIM-ready devices, delivered same-day across Lagos.',
                'category' => 'Tech & Apps',
                'listing_status' => BrandPartner::STATUS_ACTIVE,
                'is_featured' => false,
                'background_color' => '#0C8080',
                'hero_image_path' => '/images/brand-partners/sahara-connect-shop/hero.webp',
                'is_active' => true,
                'handles' => [
                    [
                        'platform' => 'instagram', 'handle_label' => 'Sahara Connect Shop', 'handle_url' => 'https://instagram.com/saharaconnectshop',
                        'credit_reward' => 5, 'verification' => 'self',
                        'last_post_image_path' => '/images/brand-partners/sahara-connect-shop/gallery-1.webp',
                        'last_post_caption' => 'Restocked: eSIM-ready devices from ₦180,000, same-day delivery within Lagos.',
                        'last_post_url' => 'https://instagram.com/saharaconnectshop', 'last_post_at' => now()->subHours(20),
                    ],
                    [
                        'platform' => 'facebook', 'handle_label' => 'Sahara Connect Shop', 'handle_url' => 'https://facebook.com/saharaconnectshop',
                        'credit_reward' => 5, 'verification' => 'self',
                    ],
                ],
                'images' => [
                    ['image_path' => '/images/brand-partners/sahara-connect-shop/hero.webp', 'caption' => 'Our workshop bench.'],
                    ['image_path' => '/images/brand-partners/sahara-connect-shop/gallery-1.webp', 'caption' => 'eSIM-ready, straight out of the box.'],
                ],
            ],
        ];
    }
}
