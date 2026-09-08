<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\BrandSettings;
use App\Support\HeroBackground;
use App\Support\MediaStorage;
use App\Support\PlatformTheme;
use App\Support\SupportSettings;
use Illuminate\Console\Command;

/**
 * Move the platform's always-on media (brand logos/favicon, dashboard-theme
 * wallpapers, the assistant avatar, dashboard hero art) from the local server
 * disk up to Wasabi once live cloud keys exist — for platform speed / CDN
 * delivery. Uploads always land locally first (MediaStorage::disk() falls back
 * to the public disk with no Wasabi keys); this command promotes the most
 * important ones to the cloud when keys appear.
 *
 * It is idempotent and safe to run on a schedule: it no-ops entirely without
 * Wasabi keys, and skips any URL that is already on the cloud/a CDN. The local
 * copy is left in place so a partial run never loses an asset.
 */
class MigrateMediaToWasabi extends Command
{
    protected $signature = 'media:migrate-to-wasabi';

    protected $description = 'Promote local platform media to Wasabi once cloud keys are live.';

    /** Scalar setting keys that hold a single media URL. */
    private const MEDIA_KEYS = [
        'brand.logo_product_light',
        'brand.logo_product_dark',
        'brand.logo_agency_light',
        'brand.logo_agency_dark',
        'brand.favicon',
        'support.avatar',
        HeroBackground::LIGHT_KEY,
        HeroBackground::DARK_KEY,
    ];

    public function handle(): int
    {
        if (! MediaStorage::wasabiConfigured()) {
            $this->info('Wasabi is not configured — media stays on the local disk. Nothing to migrate.');

            return self::SUCCESS;
        }

        $moved = 0;

        // 1) Scalar URL settings.
        foreach (self::MEDIA_KEYS as $key) {
            $url = (string) (Setting::getValue($key) ?: '');
            if ($url === '') {
                continue;
            }
            $new = MediaStorage::migrateLocalUrlToCloud($url);
            if ($new !== null) {
                Setting::setValue($key, $new);
                $moved++;
                $this->line("  moved {$key}");
            }
        }

        // 2) Dashboard-theme wallpapers (nested in one JSON config).
        $theme = PlatformTheme::current();
        $themeChanged = false;
        foreach (['image_light', 'image_dark'] as $slot) {
            $new = MediaStorage::migrateLocalUrlToCloud((string) $theme[$slot]);
            if ($new !== null) {
                $theme[$slot] = $new;
                $themeChanged = true;
                $moved++;
                $this->line("  moved platform_theme.{$slot}");
            }
        }
        if ($themeChanged) {
            PlatformTheme::save($theme);
        }

        // Refresh the cached accessors so the new cloud URLs go live immediately.
        BrandSettings::flush();
        HeroBackground::flush();
        SupportSettings::flush();
        PlatformTheme::flush();

        $this->info($moved === 0 ? 'All platform media is already on Wasabi.' : "Promoted {$moved} asset(s) to Wasabi.");

        return self::SUCCESS;
    }
}
