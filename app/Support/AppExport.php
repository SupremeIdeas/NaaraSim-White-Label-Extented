<?php

namespace App\Support;

use App\Models\Setting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Installable App Export config (App Export prompt). Everything the exported
 * Android/iOS wrapper + the public download page need is admin-editable and
 * stored in Setting rows (never hardcoded), so Frank can rename the app, bump
 * the version, swap the splash/icon and flip store-live toggles with no deploy.
 *
 * Honest-state rule (prompt §): a "ready" APK build is NOT the same as a live
 * store listing. Store badges only ever show when the admin explicitly marks
 * that listing live — a compiled artifact never implies it's published.
 */
class AppExport
{
    /** Where the download CTA can be planted (admin toggles each independently). */
    public const PLACEMENTS = [
        'admin_menu' => 'Admin side menu',
        'customer_menu' => 'Customer side menu',
        'account_settings' => 'Account settings',
        'footer' => 'Site footer',
        'homepage_card' => 'Homepage bento card',
        'purchase_confirmation' => 'Post-purchase screen',
    ];

    public const PRELOADERS = ['pulse-logo', 'spinner', 'bars'];

    /** All settings as a resolved array with defaults. */
    public static function all(): array
    {
        $d = self::defaults();
        try {
            $stored = is_array($v = Setting::getValue('appexport', [])) ? $v : [];
        } catch (\Throwable) {
            // Settings table not available yet (e.g. pre-migration) — use defaults
            // so the layout head still renders. Mirrors BrandSettings.
            return $d;
        }

        return array_merge($d, array_intersect_key($stored, $d) + ['placements' => $stored['placements'] ?? $d['placements']]);
    }

    public static function defaults(): array
    {
        return [
            'download_enabled' => false,      // public /download page live
            'app_name' => config('app.name', 'NaaraSim'),
            'short_name' => 'NaaraSim',
            'theme_color' => '#0A6E6E',
            'background_color' => '#0D1B2A',
            'version' => '1.0.0',
            'build_number' => 1,
            'changelog' => '',
            'splash_url' => '',
            'icon_url' => '',
            'preloader' => 'pulse-logo',
            'android_apk_url' => '',          // latest ready standalone APK
            'android_store_live' => false,
            'android_store_url' => '',
            'ios_store_live' => false,
            'ios_store_url' => '',
            'keystore_backed_up' => false,
            'ci_webhook_url' => '',
            'placements' => [],               // key => ['active'=>bool,'label'=>?string]

            // --- Store listing + compliance (required for Play/App Store pass) ---
            'privacy_policy_url' => '',
            'support_email' => '',
            'support_url' => '',
            'account_deletion_url' => '/account',   // both stores require a deletion path
            'category' => '',
            'content_rating' => '',
            'short_description' => '',
            'full_description' => '',
            'keywords' => '',
            'data_safety' => '',                    // Google Data Safety / Apple privacy summary
            'min_android_target_api' => 35,         // Play requires a recent target API
            'permissions_note' => '',

            // --- First-run onboarding slides (portrait), Next → login ---
            'onboarding_enabled' => true,
            'onboarding_slides' => [],              // [{image, title, subtitle}]
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Persist a validated subset (secrets handled separately, never here). */
    public static function save(array $data): void
    {
        $current = self::all();
        unset($current['placements']); // merged explicitly by caller when needed
        $merged = array_merge($current, array_intersect_key($data, self::defaults()));
        if (array_key_exists('placements', $data)) {
            $merged['placements'] = $data['placements'];
        } else {
            $merged['placements'] = self::all()['placements'];
        }

        Setting::setValue('appexport', $merged, 'appexport');
    }

    /* -------- Native/PWA state ------------------------------------------- */

    /** An Android build is downloadable only when a ready APK URL is set. */
    public static function androidDownloadable(): bool
    {
        return (bool) self::get('download_enabled') && trim((string) self::get('android_apk_url')) !== '';
    }

    public static function iosBadgeVisible(): bool
    {
        return (bool) self::get('ios_store_live') && trim((string) self::get('ios_store_url')) !== '';
    }

    public static function androidBadgeVisible(): bool
    {
        return (bool) self::get('android_store_live') && trim((string) self::get('android_store_url')) !== '';
    }

    /* -------- Placements -------------------------------------------------- */

    public static function placementActive(string $key): bool
    {
        if (! self::get('download_enabled')) {
            return false;
        }

        return (bool) (self::all()['placements'][$key]['active'] ?? false);
    }

    public static function placementLabel(string $key): string
    {
        $label = trim((string) (self::all()['placements'][$key]['label'] ?? ''));

        return $label !== '' ? $label : 'Get the app';
    }

    /* -------- PWA manifest ------------------------------------------------ */

    /** The dynamic web-app manifest (served by ManifestController). */
    public static function manifest(): array
    {
        $icon = trim((string) self::get('icon_url'));
        $iconSrc = $icon !== '' ? $icon : (BrandSettings::favicon() ?: '/favicon.ico');

        return [
            'name' => self::get('app_name'),
            'short_name' => self::get('short_name'),
            // First launch enters onboarding (self-skips once seen) → login → dashboard.
            'start_url' => self::onboardingEnabled() ? '/get-started' : '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => self::get('theme_color'),
            'background_color' => self::get('background_color'),
            'icons' => [
                ['src' => $iconSrc, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $iconSrc, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $iconSrc, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    /* -------- QR (self-hosted SVG, no external service) ------------------- */

    public static function qrSvg(string $url, int $size = 190): string
    {
        $result = (new Builder(writer: new SvgWriter(), data: $url, size: $size, margin: 8))->build();

        return $result->getString();
    }

    public static function qrDataUri(string $url, int $size = 190): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::qrSvg($url, $size));
    }

    /* -------- Onboarding slides ------------------------------------------ */

    /** @return array<int, array{image:string, title:string, subtitle:string}> */
    public static function onboardingSlides(): array
    {
        return array_values(array_filter(
            (array) self::get('onboarding_slides', []),
            fn ($s) => is_array($s) && ! empty($s['image']),
        ));
    }

    public static function onboardingEnabled(): bool
    {
        return (bool) self::get('onboarding_enabled', true) && self::onboardingSlides() !== [];
    }

    /* -------- Publish-readiness checklist -------------------------------- */

    /**
     * Every control a real Play Store / App Store submission needs, with a live
     * green/red state (App Export prompt — "make everything tick green"). The
     * items Claude Code can't satisfy (paid accounts, review) are flagged as
     * operator tasks, never silently ticked.
     *
     * @return array<string, array<int, array{label:string, ok:bool, hint:string, operator?:bool}>>
     */
    public static function publishChecklist(): array
    {
        $has = fn (string $k) => trim((string) self::get($k)) !== '';

        $shared = [
            self::item('App name set', $has('app_name'), 'Set the app name.'),
            self::item('App icon uploaded', $has('icon_url'), 'Upload a square ≥512px icon.'),
            self::item('Splash screen uploaded', $has('splash_url'), 'Upload a splash image.'),
            self::item('Version set (x.y.z)', (bool) preg_match('/^\d+\.\d+\.\d+$/', (string) self::get('version')), 'Use semantic version like 1.0.0.'),
            self::item('Privacy policy URL', $has('privacy_policy_url'), 'Required by both stores — add a public privacy policy URL.'),
            self::item('Support email or URL', $has('support_email') || $has('support_url'), 'Stores require a support contact.'),
            self::item('Account deletion path', $has('account_deletion_url'), 'Both stores require an in-app + web account-deletion path.'),
            self::item('Short description', $has('short_description'), 'Add a short store description.'),
            self::item('Full description', $has('full_description'), 'Add the full store description.'),
            self::item('Data safety / privacy summary', $has('data_safety'), 'Declare what data the app collects.'),
            self::item('Content rating', $has('content_rating'), 'Complete the content-rating questionnaire answer.'),
        ];

        $android = [
            self::item('Signing keystore stored', self::hasCredential('android_keystore'), 'Upload the release keystore (encrypted).'),
            self::item('Keystore backup confirmed', (bool) self::get('keystore_backed_up'), 'Confirm you have securely backed up the keystore.'),
            self::item('Recent target API', (int) self::get('min_android_target_api') >= 34, 'Play requires a recent target API level.'),
            self::item('A build marked ready', self::androidDownloadable(), 'Generate an APK build and mark it ready.'),
            self::operatorItem('Google Play Console account ($25 + closed test)', 'One-time fee, ID verification, and a 12-tester closed test — Frank must complete this.'),
        ];

        $ios = [
            self::item('App Store URL (when live)', $has('ios_store_url'), 'Add the App Store listing URL once created.'),
            self::operatorItem('Apple Developer Program ($99/yr)', 'Enrollment + identity verification — Frank must complete this.'),
            self::operatorItem('Cloud macOS build service', 'iOS IPA needs a macOS build environment (Codemagic/Capawesome/etc.).'),
        ];

        return ['Shared' => $shared, 'Android' => $android, 'iOS' => $ios];
    }

    public static function readinessScore(): array
    {
        $items = collect(self::publishChecklist())->flatten(1)->reject(fn ($i) => $i['operator'] ?? false);

        return ['done' => $items->where('ok', true)->count(), 'total' => $items->count()];
    }

    private static function item(string $label, bool $ok, string $hint): array
    {
        return ['label' => $label, 'ok' => $ok, 'hint' => $hint, 'operator' => false];
    }

    private static function operatorItem(string $label, string $hint): array
    {
        return ['label' => $label, 'ok' => false, 'hint' => $hint, 'operator' => true];
    }

    /* -------- Signing credentials (encrypted at rest, never logged) ------- */

    /**
     * Store a signing credential (Android keystore / iOS cert). The bytes live
     * in the `appexport.secure` Setting row, which is encrypted:array at rest;
     * the raw base64 is NEVER returned in listings or logs — only metadata.
     */
    public static function storeCredential(string $slot, string $base64, string $filename): void
    {
        $secure = self::secure();
        $secure[$slot] = [
            'data' => $base64,
            'filename' => $filename,
            'uploaded_at' => now()->toIso8601String(),
        ];
        Setting::setValue('appexport.secure', $secure, 'appexport');

        // A freshly uploaded Android keystore un-acks the backup warning.
        if ($slot === 'android_keystore') {
            self::save(['keystore_backed_up' => false]);
        }
    }

    public static function hasCredential(string $slot): bool
    {
        return isset(self::secure()[$slot]['data']);
    }

    /** Metadata only — filename + uploaded_at, never the bytes. */
    public static function credentialMeta(string $slot): ?array
    {
        $c = self::secure()[$slot] ?? null;
        if (! $c) {
            return null;
        }

        return ['filename' => $c['filename'] ?? '', 'uploaded_at' => $c['uploaded_at'] ?? ''];
    }

    /** @return array<string, array> */
    private static function secure(): array
    {
        try {
            $v = Setting::getValue('appexport.secure', []);
        } catch (\Throwable) {
            return [];
        }

        return is_array($v) ? $v : [];
    }
}
