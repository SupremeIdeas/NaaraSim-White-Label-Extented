<?php

namespace Database\Seeders;

use App\Models\EsimCountryImage;
use App\Models\EsimRegionImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the shipped country + region navigation imagery (BUILD-8 §2/§7).
 *
 * The webp assets live in public/images/esim/{countries,regions}, named by ISO2
 * (country) or region slug. This maps each file to an esim_country_images /
 * esim_region_images row so the §3 navigation grid shows real imagery out of the
 * box. Admins can override any of these later via the eSIM Control Center.
 *
 * Idempotent (updateOrCreate) and it never clobbers an admin-uploaded image:
 * only rows still pointing at a shipped /images/esim/ path are refreshed.
 */
class EsimImageSeeder extends Seeder
{
    public function run(): void
    {
        $base = public_path('images/esim');

        // Country cutouts → grid icon AND detail image (rendered as a compact
        // side thumbnail on the country page/plan detail, never a full-bleed
        // cover — see catalogue.blade.php).
        foreach (glob($base.'/countries/*.webp') ?: [] as $file) {
            $iso = strtoupper(Str::of(basename($file, '.webp'))->upper());
            if (strlen($iso) !== 2) {
                continue;
            }
            $url = '/images/esim/countries/'.basename($file);

            $row = EsimCountryImage::firstOrNew(['country_code' => $iso]);
            if ($row->exists && ! Str::startsWith((string) $row->icon_path, '/images/esim/')) {
                continue; // admin-customised — leave it alone
            }
            $row->icon_path = $url;
            $row->detail_image_path = $url;
            $row->save();
        }

        // Region maps → both grid icon AND detail banner (they read well large).
        foreach (glob($base.'/regions/*.webp') ?: [] as $file) {
            $slug = basename($file, '.webp');
            $url = '/images/esim/regions/'.basename($file);

            $row = EsimRegionImage::firstOrNew(['region_slug' => $slug]);
            if ($row->exists && ! Str::startsWith((string) $row->icon_path, '/images/esim/')) {
                continue; // admin-customised — leave it alone
            }
            $row->icon_path = $url;
            $row->detail_image_path = $url;
            $row->save();
        }
    }
}
