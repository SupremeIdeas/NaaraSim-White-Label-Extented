<?php

namespace Database\Seeders;

use App\Models\WhiteLabelGuideLink;
use Illuminate\Database\Seeder;

/**
 * Owner request (2026-09-15) — starter reference links for the in-app
 * Merchant White Label Guide, matching the exact providers already named in
 * the project intake form's own hosting copy (Cloudways for VPS, Hostinger/
 * Namecheap for shared hosting and domains) so the guide and the intake form
 * never point a merchant at two different recommendations. Plain,
 * non-affiliate URLs by design — the admin swaps these for their own
 * affiliate link per row from Admin → White Label → Guide Links, with no
 * code change needed.
 */
class WhiteLabelGuideLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            [
                'category' => WhiteLabelGuideLink::CATEGORY_DOMAIN,
                'label' => 'Namecheap — register your domain',
                'url' => 'https://www.namecheap.com/domains/',
                'description' => 'Search and register the domain your platform will live on.',
                'sort_order' => 1,
            ],
            [
                'category' => WhiteLabelGuideLink::CATEGORY_VPS,
                'label' => 'Cloudways — Laravel-optimized VPS hosting',
                'url' => 'https://www.cloudways.com/en/',
                'description' => 'The VPS host our deployment team is set up to work with directly.',
                'sort_order' => 1,
            ],
            [
                'category' => WhiteLabelGuideLink::CATEGORY_SHARED,
                'label' => 'Hostinger — premium shared hosting',
                'url' => 'https://www.hostinger.com/',
                'description' => 'A straightforward shared-hosting option that meets the platform\'s requirements.',
                'sort_order' => 1,
            ],
            [
                'category' => WhiteLabelGuideLink::CATEGORY_SHARED,
                'label' => 'Namecheap — shared hosting',
                'url' => 'https://www.namecheap.com/hosting/shared/',
                'description' => 'An alternative shared-hosting option, from the same registrar as your domain.',
                'sort_order' => 2,
            ],
        ];

        foreach ($links as $link) {
            WhiteLabelGuideLink::updateOrCreate(
                ['category' => $link['category'], 'label' => $link['label']],
                $link + ['is_active' => true]
            );
        }
    }
}
