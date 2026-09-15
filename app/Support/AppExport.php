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
            // Master kill-switch for the whole App Export layer (owner request,
            // 2026-09-16): when off, the admin App Builder screen 404s (never a
            // bare 403 — a closed feature should look like it doesn't exist,
            // matching the FeatureFlags/FeatureLocks house convention), the
            // public /download page 404s regardless of download_enabled, and a
            // build can't be triggered even via a direct BuildDispatcher call.
            // Defaults ON so an already-configured install's App Export keeps
            // working exactly as before — this is an OFF switch to close it,
            // not an opt-in gate.
            'app_export_enabled' => true,
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
            // CI provider (owner audit, 2026-09-15 — Codemagic reference doc;
            // revised 2026-09-15 per docs/APP-EXPORT.md + the merchant-app-export
            // blueprint's Stage 1: Android and iOS use DIFFERENT CI, not one
            // shared toggle. Android always builds via this repo's own
            // .github/workflows/android-build.yml on GitHub Actions (cheap, no
            // macOS runner) — github_repo/github_branch below are ALL it needs.
            // `ci_provider` now governs iOS ONLY: 'generic' posts the original
            // signed webhook payload to ci_webhook_url (any custom macOS-CI
            // receiver); 'codemagic' calls Codemagic's real REST API directly
            // (docs.codemagic.io) using codemagic_app_id/codemagic_ios_workflow_id
            // + the ProviderKeys-stored token.
            'ci_provider' => 'generic',
            'ci_webhook_url' => '',
            'codemagic_app_id' => '',
            'codemagic_ios_workflow_id' => '',
            'codemagic_branch' => 'main',
            // Android CI — this repo's own GitHub Actions workflow, fired via
            // repository_dispatch. owner/repo, e.g. "SupremeIdeas/NaaraSim".
            'github_repo' => '',
            'github_branch' => 'main',
            // §1.5/§3.5 — honest visibility for the one thing NaaraSim itself
            // cannot do (sign an .ipa): an admin who configured Apple signing
            // directly on the CI provider's own dashboard (the common path for
            // an App Store Connect API key) checks this instead of leaving a
            // silent gap.
            'ios_signing_on_provider' => false,
            // App Store payments-compliance doc (BUILD-5 §6): gift cards read as
            // "digital gift cards redeemed for digital goods" — Apple's IAP
            // territory. Default OFF on iOS until a per-brand determination is
            // made; the admin can flip this on once that review is done.
            'ios_gift_cards_enabled' => false,
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

    /** The master App Export kill-switch (owner request, 2026-09-16). */
    public static function enabled(): bool
    {
        return (bool) self::get('app_export_enabled', true);
    }

    /**
     * Whether a real CI provider is actually reachable right now — the exact
     * gap the 2026-09-15 audit found (a blank config here used to leave every
     * build silently stuck at "Queued" forever, since no self-hosted runner
     * ever existed to pick it up). Split per-platform (2026-09-15 revision):
     * Android and iOS use genuinely different CI, not one shared toggle — see
     * `ci_provider`'s docblock above.
     */
    public static function androidCiConfigured(): bool
    {
        return trim((string) config('services.appexport.github_token', '')) !== ''
            && trim((string) self::get('github_repo')) !== '';
    }

    public static function iosCiConfigured(): bool
    {
        if ((string) self::get('ci_provider', 'generic') === 'codemagic') {
            return trim((string) config('services.appexport.codemagic_api_token', '')) !== ''
                && trim((string) self::get('codemagic_app_id')) !== ''
                && trim((string) self::get('codemagic_ios_workflow_id')) !== '';
        }

        return trim((string) self::get('ci_webhook_url')) !== '';
    }

    /** Either platform's CI is configured — used only where a single yes/no is needed. */
    public static function ciConfigured(): bool
    {
        return self::androidCiConfigured() || self::iosCiConfigured();
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
            // Android CI (owner audit, 2026-09-15): a build with nowhere real to
            // compile used to sit silently "Queued" forever — surface it here so
            // that state is visible before anyone clicks Generate, not after.
            self::item('GitHub Actions CI configured', self::androidCiConfigured(), 'Set the GitHub token (Admin → API Keys) and github_repo below — Android builds fire the repo\'s own android-build.yml via repository_dispatch.'),
            self::item('Signing keystore stored', self::hasCredential('android_keystore'), 'Upload the release keystore (encrypted).'),
            self::item('Keystore backup confirmed', (bool) self::get('keystore_backed_up'), 'Confirm you have securely backed up the keystore.'),
            self::item('Recent target API', (int) self::get('min_android_target_api') >= 34, 'Play requires a recent target API level.'),
            self::item('A build marked ready', self::androidDownloadable(), 'Generate an APK build and mark it ready.'),
            self::operatorItem('Google Play Console account ($25 + closed test)', 'One-time fee, ID verification, and a 12-tester closed test — Frank must complete this.'),
        ];

        // iOS signing (owner audit, 2026-09-15, §1.5/§3.5): NaaraSim itself has
        // no code path that can sign an .ipa — either the credentials are
        // uploaded here (so a provider whose API accepts pushed credentials
        // can use them) or they're configured directly on the CI provider's
        // own dashboard. Both are shown honestly rather than a silent gap.
        $ios = [
            self::item('iOS CI provider configured', self::iosCiConfigured(), 'Set a CI webhook URL, or pick Codemagic and fill in its app/workflow id + API token, in the section below — otherwise an iOS build will fail immediately instead of compiling.'),
            self::item(
                'iOS signing (stored here or on the CI provider)',
                (self::hasCredential('ios_cert') && self::hasCredential('ios_provisioning_profile')) || (bool) self::get('ios_signing_on_provider'),
                'Upload the distribution certificate + provisioning profile below, OR configure them directly on your CI provider\'s dashboard (common for App Store Connect API keys) and check the box confirming that.',
            ),
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
