<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * App Studio — the native-app configuration surface (NAARA-BUILD-21). Extends the
 * App Export config (AppExport) with everything a real app-wrapper build needs
 * beyond name/icon/version: initial URL, package IDs, the offline page, native
 * link-handling rules, permission usage-descriptions, and the network-security
 * policy. Grouped to match the proven Median/FlangApp structure.
 *
 * Governing principle (blueprint §0): every field auto-populates from real Naara
 * platform data by default, so "Generate Build" works with ZERO fields touched.
 * Stored overrides are only applied when the admin actually sets one — the getter
 * methods below fall back to the platform-derived default whenever a value is blank.
 */
class AppStudio
{
    public const SETTING_KEY = 'appstudio';

    public const OFFLINE_STYLES = ['default', 'custom'];

    public const LINK_ACTIONS = ['internal', 'app_browser', 'external'];

    /** Social hosts that should open in the in-app browser, not the webview or the OS. */
    private const SOCIAL_HOSTS = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'linkedin.com', 'whatsapp.com', 'wa.me', 'tiktok.com', 'youtube.com'];

    /* -------- Raw storage ------------------------------------------------- */

    public static function all(): array
    {
        $d = self::defaults();
        try {
            $stored = Setting::getValue(self::SETTING_KEY, []);
        } catch (\Throwable) {
            return $d;
        }
        $stored = is_array($stored) ? $stored : [];

        return array_merge($d, array_intersect_key($stored, $d));
    }

    public static function defaults(): array
    {
        return [
            // General
            'initial_url' => '',            // blank → platform login/home URL
            'app_display_name' => '',       // blank → brand name
            'android_package_name' => '',   // blank → derived from brand (set-once, warn on change)
            'ios_bundle_id' => '',          // blank → derived from brand
            // Branding
            'icon_source' => 'brand',       // brand | custom (custom = AppExport icon_url)
            'splash_style' => 'icon',       // icon | full
            // Interface — offline page
            'offline_style' => 'default',   // default | custom
            'offline_timeout' => 10,        // seconds of no connectivity before the offline page
            'offline_html' => '',           // blank → branded default page
            'pull_to_refresh' => true,
            'refresh_button' => false,
            // Native navigation
            'link_rules' => [],             // blank → auto-populated default rule list
            'deep_link_domain' => '',       // blank → host from APP_URL
            // Permissions: key => ['enabled' => bool, 'description' => string(blank→default)]
            'permissions' => [],
            // Security
            'disallow_insecure_http' => true,
            'bridge_restrict_own_domain' => true,
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Persist a validated subset — unknown keys are dropped. */
    public static function save(array $data): void
    {
        $merged = array_merge(self::all(), array_intersect_key($data, self::defaults()));
        Setting::setValue(self::SETTING_KEY, $merged, self::SETTING_KEY);
    }

    /* -------- General (auto-populated getters) --------------------------- */

    public static function initialUrl(): string
    {
        $v = trim((string) self::get('initial_url'));

        return $v !== '' ? $v : rtrim((string) config('app.url'), '/').'/dashboard';
    }

    public static function displayName(): string
    {
        $v = trim((string) self::get('app_display_name'));

        return $v !== '' ? $v : BrandSettings::name();
    }

    /** com.<vendor>.<brand> derived from the brand name, once. Admin can override. */
    public static function androidPackage(): string
    {
        $v = trim((string) self::get('android_package_name'));

        return $v !== '' ? $v : self::derivedPackage();
    }

    public static function iosBundle(): string
    {
        $v = trim((string) self::get('ios_bundle_id'));

        return $v !== '' ? $v : self::derivedPackage();
    }

    private static function derivedPackage(): string
    {
        $brand = Str::of(BrandSettings::name())->slug('')->lower()->toString();
        $brand = $brand !== '' ? $brand : 'app';

        return 'com.supremeideas.'.$brand;
    }

    public static function deepLinkDomain(): string
    {
        $v = trim((string) self::get('deep_link_domain'));
        if ($v !== '') {
            return $v;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'naara.app');
    }

    /* -------- Interface — offline page ----------------------------------- */

    public static function offlineIsCustom(): bool
    {
        return self::get('offline_style') === 'custom' && trim((string) self::get('offline_html')) !== '';
    }

    /** The offline page HTML that ships in the build (custom if set, else branded default). */
    public static function offlineHtml(): string
    {
        if (self::offlineIsCustom()) {
            return (string) self::get('offline_html');
        }

        return self::defaultOfflineHtml();
    }

    /** A genuinely-branded default offline page — not a generic "no connection" icon. */
    public static function defaultOfflineHtml(): string
    {
        $name = e(self::displayName());
        $primary = BrandSettings::color('primary') ?: '#0A6E6E';
        $navy = BrandSettings::color('navy') ?: '#0D1B2A';

        return <<<HTML
        <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <style>
          :root { color-scheme: light dark; }
          html,body { height:100%; margin:0; font-family:-apple-system,Segoe UI,Roboto,system-ui,sans-serif; }
          body { display:flex; align-items:center; justify-content:center; text-align:center;
                 background:{$navy}; color:#fff; padding:2rem; }
          .wrap { max-width:20rem; }
          .dot { width:64px; height:64px; margin:0 auto 1.25rem; border-radius:50%;
                 background:{$primary}; display:flex; align-items:center; justify-content:center; }
          .dot svg { width:32px; height:32px; stroke:#fff; fill:none; stroke-width:2; }
          h1 { font-size:1.25rem; margin:0 0 .5rem; }
          p { opacity:.75; font-size:.9rem; line-height:1.5; margin:0 0 1.5rem; }
          button { background:{$primary}; color:#fff; border:0; border-radius:.75rem;
                   padding:.75rem 1.5rem; font-size:.9rem; font-weight:600; }
        </style></head><body><div class="wrap">
          <div class="dot"><svg viewBox="0 0 24 24"><path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0119 12.55M5 12.55a10.94 10.94 0 015.17-2.39M10.71 5.05A16 16 0 0122.58 9M1.42 9a15.91 15.91 0 014.7-2.88M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg></div>
          <h1>You're offline</h1>
          <p>{$name} needs a connection to load. Check your data or Wi-Fi and try again.</p>
          <button onclick="location.reload()">Retry</button>
        </div></body></html>
        HTML;
    }

    /* -------- Native navigation — link handling -------------------------- */

    /**
     * The ordered link-handling rule list. Falls back to sane Naara defaults:
     * own domain → internal, social → in-app browser, everything else → external.
     *
     * @return array<int, array{pattern:string, action:string}>
     */
    public static function linkRules(): array
    {
        $stored = self::get('link_rules');
        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter($stored, fn ($r) => is_array($r) && ! empty($r['pattern'])));
        }

        return self::defaultLinkRules();
    }

    /** @return array<int, array{pattern:string, action:string}> */
    public static function defaultLinkRules(): array
    {
        $host = self::deepLinkDomain();
        $rules = [['pattern' => $host, 'action' => 'internal']];
        foreach (self::SOCIAL_HOSTS as $h) {
            $rules[] = ['pattern' => $h, 'action' => 'app_browser'];
        }
        $rules[] = ['pattern' => '*', 'action' => 'external'];

        return $rules;
    }

    /* -------- Permissions ------------------------------------------------- */

    /**
     * Permission toggles + usage-description strings, merged over the platform's
     * real, specific defaults (a blank description is an App Store rejection risk).
     * Only permissions Naara actually uses default to enabled.
     *
     * @return array<string, array{label:string, enabled:bool, description:string}>
     */
    public static function permissions(): array
    {
        $stored = (array) self::get('permissions');
        $out = [];
        foreach (self::permissionDefaults() as $key => $def) {
            $s = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $desc = trim((string) ($s['description'] ?? ''));
            $out[$key] = [
                'label' => $def['label'],
                'enabled' => array_key_exists('enabled', $s) ? (bool) $s['enabled'] : $def['enabled'],
                'description' => $desc !== '' ? $desc : $def['description'],
            ];
        }

        return $out;
    }

    /** @return array<string, array{label:string, enabled:bool, description:string}> */
    public static function permissionDefaults(): array
    {
        $n = self::displayName();

        return [
            'camera' => [
                'label' => 'Camera',
                'enabled' => true, // KYC document capture is a real reason today
                'description' => "$n needs camera access to scan and upload your identity verification document.",
            ],
            'microphone' => [
                'label' => 'Microphone',
                'enabled' => true, // in-app voice calls on virtual numbers
                'description' => "$n needs microphone access for voice calls on your virtual numbers.",
            ],
            'webrtc' => [
                'label' => 'WebRTC audio/video',
                'enabled' => true,
                'description' => "$n uses a secure connection to place and receive calls from your numbers.",
            ],
            'geolocation' => [
                'label' => 'Geolocation',
                'enabled' => false, // only if a location feature is switched on
                'description' => "$n uses your location to suggest data plans and numbers available in your region.",
            ],
            'att' => [
                'label' => 'iOS App Tracking Transparency',
                'enabled' => false,
                'description' => "$n asks your permission before any activity is used to personalise your experience.",
            ],
        ];
    }

    /* -------- Push provider status --------------------------------------- */

    /**
     * Reuse the web push provider for native rather than adding a second one.
     * Reports which existing channel can serve native push and flags any gap,
     * rather than silently wiring OneSignal (blueprint §2.6).
     *
     * @return array{provider:?string, ready:bool, note:string}
     */
    public static function pushStatus(): array
    {
        // WhatsApp Autopilot is the wired lifecycle-notification channel today.
        $whatsapp = trim((string) config('services.whatsapp.access_token')) !== '';
        // Firebase (FCM) would be the native push provider; detected via env.
        $firebase = trim((string) env('FIREBASE_SERVER_KEY', env('FCM_SERVER_KEY', ''))) !== '';

        if ($firebase) {
            return ['provider' => 'Firebase (FCM)', 'ready' => true,
                'note' => 'The existing Firebase/FCM credentials serve native push — no second provider needed.'];
        }

        if ($whatsapp) {
            return ['provider' => 'WhatsApp Autopilot', 'ready' => false,
                'note' => 'WhatsApp Autopilot handles lifecycle messages but is not a native push channel. Add Firebase (FCM) credentials to enable native push — do NOT add a second push vendor without confirming this gap first.'];
        }

        return ['provider' => null, 'ready' => false,
            'note' => 'No push provider is configured yet. Add Firebase (FCM) credentials to serve BOTH web and native push from one provider.'];
    }

    /* -------- Build readiness -------------------------------------------- */

    /**
     * True — every field has a platform-derived default, so a build can be
     * generated with zero manual input. Present as an explicit signal in the UI.
     */
    public static function canGenerateWithDefaults(): bool
    {
        return self::initialUrl() !== '' && self::displayName() !== '' && self::androidPackage() !== '';
    }

    /**
     * The fully-resolved native app config (all defaults applied), grouped for
     * the admin UI + preview. Every value is populated even when nothing was
     * touched. For the actual compile payload use medianConfig() below.
     */
    public static function buildConfig(): array
    {
        return [
            'general' => [
                'initial_url' => self::initialUrl(),
                'app_display_name' => self::displayName(),
                'android_package_name' => self::androidPackage(),
                'ios_bundle_id' => self::iosBundle(),
            ],
            'branding' => [
                'icon_source' => self::get('icon_source'),
                'icon_url' => trim((string) AppExport::get('icon_url')) ?: (BrandSettings::favicon() ?: ''),
                'splash_url' => (string) AppExport::get('splash_url'),
                'splash_style' => self::get('splash_style'),
                'theme_color' => (string) AppExport::get('theme_color'),
                'background_color' => (string) AppExport::get('background_color'),
            ],
            'interface' => [
                'offline_style' => self::get('offline_style'),
                'offline_timeout' => (int) self::get('offline_timeout'),
                'offline_html' => self::offlineHtml(),
                'pull_to_refresh' => (bool) self::get('pull_to_refresh'),
                'refresh_button' => (bool) self::get('refresh_button'),
            ],
            'navigation' => [
                'link_rules' => self::linkRules(),
                'deep_link_domain' => self::deepLinkDomain(),
                'sidebar_menu' => self::sidebarMenu(),
            ],
            'permissions' => self::permissions(),
            'push' => self::pushStatus(),
            'security' => [
                'disallow_insecure_http' => (bool) self::get('disallow_insecure_http'),
                'bridge_restrict_own_domain' => (bool) self::get('bridge_restrict_own_domain'),
            ],
        ];
    }

    /* -------- Native sidebar menu (auto-populated from real routes) ------- */

    /**
     * The app's sidebar/drawer menu, auto-populated from real platform routes so
     * the admin never re-enters links the platform already knows (blueprint
     * §2.4.2). Matches the Median `sidebarNavigation.menus[].items` shape.
     *
     * @return array<int, array{url:string, label:string, subLinks:array}>
     */
    public static function sidebarMenu(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $items = [
            [$base.'/', 'Home'],
            [$base.'/register', 'Create an account'],
            [$base.'/forgot-password', 'Forgot password?'],
            [$base.'/legal/terms', 'Terms'],
            [$base.'/legal/privacy', 'Privacy'],
            [$base.'/refund-policy', 'Refunds'],
            [$base.'/status', 'Status'],
        ];

        return array_map(fn ($i) => ['url' => $i[0], 'label' => $i[1], 'subLinks' => []], $items);
    }

    /* -------- Real Median appConfig export ------------------------------- */

    /**
     * The fully-resolved config in the REAL Median `appConfig.json` shape,
     * grounded against an actual Median-generated build of this platform. This
     * is what ships to the compile backend, so a generated app is genuinely
     * Median-compatible rather than an invented schema.
     */
    public static function medianConfig(): array
    {
        $short = BrandSettings::name();
        $pull = (bool) self::get('pull_to_refresh');
        $refresh = (bool) self::get('refresh_button');
        $timeout = (int) self::get('offline_timeout');
        $perms = self::permissions();
        $enabled = fn (string $k) => (bool) ($perms[$k]['enabled'] ?? false);
        $desc = fn (string $k) => (string) ($perms[$k]['description'] ?? '');
        $push = self::pushStatus();

        return [
            'general' => [
                'initialUrl' => self::initialUrl(),
                'appName' => self::displayName(),
                'userAgentAdd' => 'median',
                'enableWindowOpen' => true,
                'androidPackageName' => self::androidPackage(),
                'iosBundleId' => self::iosBundle(),
                'iosUserAgentAdd' => $short,
                'androidUserAgentAdd' => $short,
                'nativeBridgeUrls' => self::nativeBridgeUrls(),
                'version' => 1,
            ],
            'navigation' => [
                'androidPullToRefresh' => $pull,
                'iosPullToRefresh' => $pull,
                'androidShowOfflinePage' => true,
                'iosShowOfflinePage' => true,
                'androidConnectionOfflineTime' => $timeout,
                'iosConnectionOfflineTime' => $timeout,
                'androidShowRefreshButton' => $refresh,
                'iosShowRefreshButton' => $refresh,
                'deepLinkDomains' => [
                    'domains' => [self::deepLinkDomain()],
                    'enableAndroidApplinks' => true,
                ],
                'sidebarNavigation' => [
                    'menus' => [[
                        'active' => true,
                        'name' => 'default',
                        'items' => self::sidebarMenu(),
                    ]],
                ],
                'regexInternalExternal' => [
                    'rules' => self::medianLinkRules(),
                    'active' => true,
                ],
            ],
            'styling' => [
                'icon' => trim((string) AppExport::get('icon_url')) ?: (BrandSettings::favicon() ?: ''),
                'splashScreen' => (string) AppExport::get('splash_url'),
                'iosDarkMode' => 'auto',
                'androidTheme' => 'auto',
                'androidAccentColor' => BrandSettings::color('primary'),
                'androidBackgroundColor' => (string) AppExport::get('background_color'),
                'showNavigationBar' => false,
            ],
            'permissions' => [
                'usesGeolocation' => $enabled('geolocation'),
                'enableWebRTCamera' => $enabled('webrtc') || $enabled('camera'),
                'enableWebRTCMicrophone' => $enabled('webrtc') || $enabled('microphone'),
                'androidDownloadToPublicStorage' => true,
                'iosLocationUsageDescription' => $enabled('geolocation') ? $desc('geolocation') : '',
                'iosCameraUsageDescription' => $enabled('camera') ? $desc('camera') : '',
                'iosPhotoLibraryUsageDescription' => $enabled('camera') ? $desc('camera') : '',
                'iosMicrophoneUsageDescription' => $enabled('microphone') ? $desc('microphone') : '',
                'iOSATTUserTrackingDescription' => $enabled('att') ? $desc('att') : '',
                'iOSRequestATTConsentOnLoad' => $enabled('att'),
            ],
            'services' => [
                // Reuse the existing web push provider — do NOT silently wire a
                // second vendor. active stays false until the gap in pushStatus()
                // is resolved with real credentials (blueprint §2.6).
                'oneSignalV5' => ['active' => false],
                'pushProvider' => $push['provider'],
                'pushReady' => $push['ready'],
            ],
            'security' => [
                'network' => [
                    // allowInsecure=false blocks http:// content; our default is to
                    // disallow it, matching modern store requirements.
                    'allowInsecure' => ! (bool) self::get('disallow_insecure_http'),
                ],
                'nativeBridgeRestrictToDomain' => (bool) self::get('bridge_restrict_own_domain'),
            ],
        ];
    }

    /** When the bridge is domain-restricted, only our own origin may use it. */
    private static function nativeBridgeUrls(): array
    {
        if (! (bool) self::get('bridge_restrict_own_domain')) {
            return [];
        }
        $host = preg_quote(self::deepLinkDomain(), '/');

        return ['https?:\/\/([-\w]+\.)*'.$host.'(\/.*)?$'];
    }

    /**
     * The admin's friendly (pattern, action) rules translated into the real
     * Median regexInternalExternal rule shape (regex + mode + label).
     *
     * @return array<int, array{regex:string, mode:string, label:string, pagesToTrigger:string}>
     */
    public static function medianLinkRules(): array
    {
        // Non-web links (tel:, mailto:, …) always go to the OS — matches Median.
        $rules = [[
            'regex' => '^(?!https?://).*',
            'mode' => 'external',
            'label' => 'Non-web links',
            'pagesToTrigger' => 'custom',
        ]];

        foreach (self::linkRules() as $r) {
            $pattern = (string) $r['pattern'];
            $mode = match ($r['action']) {
                'internal' => 'internal',
                'external' => 'external',
                default => 'appbrowser',
            };
            if ($pattern === '*') {
                $rules[] = ['regex' => '.*', 'mode' => $mode, 'label' => 'All other links', 'pagesToTrigger' => 'all'];

                continue;
            }
            $host = preg_quote($pattern, '/');
            $rules[] = [
                'regex' => 'https?:\/\/([-\w]+\.)*'.$host.'(\/.*)?$',
                'mode' => $mode,
                'label' => $pattern,
                'pagesToTrigger' => ($mode === 'internal' ? 'domain' : 'custom'),
            ];
        }

        return $rules;
    }
}
