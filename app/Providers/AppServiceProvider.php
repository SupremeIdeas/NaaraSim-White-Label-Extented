<?php

namespace App\Providers;

use App\Events\CircuitClosed;
use App\Events\CircuitOpened;
use App\Events\HealthCheckCompleted;
use App\Events\PayoutReversed;
use App\Events\ProviderOutcomeRecorded;
use App\Listeners\RecordLoginDevice;
use App\Listeners\ReturnMerchantEarnings;
use App\Listeners\ReturnPartnerEarnings;
use App\Listeners\ReturnPlatformEarnings;
use App\Listeners\ReturnReferralEarnings;
use App\Listeners\ReturnStaffEarnings;
use App\Listeners\ReturnWithdrawnCredits;
use App\Models\Banner;
use App\Models\NumbersBentoCard;
use App\Models\Setting;
use App\Notifications\Channels\WebPushChannel;
use App\Services\eSIM\AiraloService;
use App\Services\eSIM\EsimAccessService;
use App\Services\eSIM\EsimGoService;
use App\Services\eSIM\GigsService;
use App\Services\eSIM\MontyMobileService;
use App\Services\eSIM\OneGlobalService;
use App\Services\eSIM\QuibityService;
use App\Services\eSIM\UbigiService;
use App\Services\eSIM\ZenditService;
use App\Services\Kyc\DojahKycProvider;
use App\Services\Kyc\KycService;
use App\Services\Kyc\ManualKycProvider;
use App\Services\Kyc\SmileIdKycProvider;
use App\Services\Maintenance\ClaudeFixProposer;
use App\Services\Maintenance\Contracts\CodeHostClient;
use App\Services\Maintenance\Contracts\FixProposer;
use App\Services\Maintenance\GitHubCodeHostClient;
use App\Services\NCI\Listeners\IncrementNciScore;
use App\Services\NCI\Listeners\RecomputeProviderScore;
use App\Services\NCI\Listeners\RefreshNciOnHealthCheck;
use App\Services\Payments\BinancePayGateway;
use App\Services\Payments\CoinPaymentsGateway;
use App\Services\Payments\CryptomusGateway;
use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\NowPaymentsGateway;
use App\Services\Payments\PaypalGateway;
use App\Services\Payments\PayssionGateway;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Payouts\CryptomusPayoutGateway;
use App\Services\Payouts\FlutterwaveBankResolver;
use App\Services\Payouts\FlutterwavePayoutGateway;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayPalPayoutGateway;
use App\Services\Payouts\PaystackBankResolver;
use App\Services\Payouts\PaystackPayoutGateway;
use App\Services\Payouts\StripePayoutGateway;
use App\Services\Pricing\PricingEngine;
use App\Services\Push\MinishlinkPushSender;
use App\Services\Push\WebPushSender;
use App\Services\SMS\FiveSimService;
use App\Services\SMS\GetatextService;
use App\Services\SMS\HeroSmsService;
use App\Services\SMS\Numbers\SinchService;
use App\Services\SMS\Numbers\TelnyxService;
use App\Services\SMS\Numbers\TwilioService;
use App\Services\SMS\Numbers\VonageService;
use App\Services\SMS\OnlineSimService;
use App\Services\SMS\PlivoService;
use App\Services\SMS\SmsPoolService;
use App\Services\SMS\SonetelService;
use App\Services\SMS\VirtSmsService;
use App\Services\Support\ClaudeChatModel;
use App\Services\Support\Contracts\ChatModel;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Services\Support\ElevenLabsVoice;
use App\Services\Wallet\WalletService;
use App\Support\Banners;
use App\Support\BentoIcons;
use App\Support\BrandSettings;
use App\Support\CreditSettings;
use App\Support\EnvironmentGuard;
use App\Support\EsimHeroContent;
use App\Support\FeatureFlags;
use App\Support\GatewayCredentials;
use App\Support\Geo\CloudflareGeoResolver;
use App\Support\Geo\GeoResolver;
use App\Support\GiftHeroBackground;
use App\Support\HeroBackground;
use App\Support\IconOverrides;
use App\Support\Installer;
use App\Support\LegalContent;
use App\Support\MailSettings;
use App\Support\MediaStorage;
use App\Support\NumberCatalogue;
use App\Support\NumbersBento;
use App\Support\NumbersHeroContent;
use App\Support\PaymentGatewayConfig;
use App\Support\PreloaderSettings;
use App\Support\ProviderKeys;
use App\Support\SchedulerHealth;
use App\Support\SecuritySettings;
use App\Support\ServiceIcons;
use App\Support\SiteChrome;
use App\Support\SiteContent;
use App\Support\SplashSettings;
use App\Support\SupportAutopilot;
use App\Support\SupportSettings;
use App\Support\TaxRates;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fresh-upload safeguards (blueprint S22): before the web installer runs,
        // seed an APP_KEY (else the encrypting middleware 500s with no .env) and
        // force file-based session/cache (else StartSession queries a database
        // that doesn't exist yet). Both no-op once the app is installed.
        Installer::bootstrapKey();
        Installer::useSafeDriversUntilInstalled();

        // PricingEngine is the single owner of all price math (blueprint 1.4).
        $this->app->singleton(PricingEngine::class);

        // WalletService is the single owner of wallet balance changes (1.2).
        $this->app->singleton(WalletService::class);

        // eSIM providers, resolved by name via app("esim.$provider") — one
        // interface, one router (blueprint Section 5.1). Swappable by design.
        $this->app->singleton('esim.esimgo', EsimGoService::class);
        $this->app->singleton('esim.airalo', AiraloService::class);
        $this->app->singleton('esim.quibity', QuibityService::class);
        // Full-eSIM providers (Naara Connect: calls + data). Zendit also serves
        // plain data eSIMs, so it doubles as a data-lane backup.
        $this->app->singleton('esim.zendit', ZenditService::class);
        $this->app->singleton('esim.oneglobal', OneGlobalService::class);
        $this->app->singleton('esim.montymobile', MontyMobileService::class);
        $this->app->singleton('esim.gigs', GigsService::class);
        // NAARA-BUILD-18 — new eSIM adapters, registered so they appear in the
        // Operations Center. Shipped enabled=false; keys added when onboarded.
        $this->app->singleton('esim.esimaccess', EsimAccessService::class);
        $this->app->singleton('esim.ubigi', UbigiService::class); // placeholder tier

        // Number providers, resolved by name via app("number.$provider").
        // OTP/rental lane (SmsProviderInterface): Getatext (US), 5sim (global),
        // HeroSMS (SMS-Activate successor — full rent), VirtSMS (fallback).
        // Permanent lane (NumberProviderInterface): Twilio (primary), Telnyx
        // (backup). Router never crosses lanes (S11).
        $this->app->singleton('number.getatext', GetatextService::class);
        $this->app->singleton('number.fivesim', FiveSimService::class);
        $this->app->singleton('number.herosms', HeroSmsService::class);
        $this->app->singleton('number.virtsms', VirtSmsService::class);
        $this->app->singleton('number.twilio', TwilioService::class);
        $this->app->singleton('number.telnyx', TelnyxService::class);
        // NAARA-BUILD-18 — new number adapters (enabled=false until onboarded).
        $this->app->singleton('number.smspool', SmsPoolService::class);
        $this->app->singleton('number.onlinesim', OnlineSimService::class);
        $this->app->singleton('number.plivo', PlivoService::class);
        $this->app->singleton('number.sonetel', SonetelService::class); // placeholder tier
        // Prompt 12 §2/§3 — Vonage/Sinch join the naara_line failover lane.
        $this->app->singleton('number.vonage', VonageService::class);
        $this->app->singleton('number.sinch', SinchService::class);

        // Web-push sender (self-hosted VAPID) — swapped for a fake in tests.
        $this->app->bind(WebPushSender::class, MinishlinkPushSender::class);

        // Geo resolver for the admin country allow-list — Cloudflare header by
        // default (zero dependency); a deployer can bind a GeoLite2/API resolver.
        $this->app->bind(GeoResolver::class, CloudflareGeoResolver::class);

        // Payment gateways, resolved by name via app("pay.$gateway").
        $this->app->singleton('pay.flutterwave', FlutterwaveGateway::class);
        $this->app->singleton('pay.paystack', PaystackGateway::class);
        $this->app->singleton('pay.stripe', StripeGateway::class);
        $this->app->singleton('pay.paypal', PaypalGateway::class);
        $this->app->singleton('pay.binance', BinancePayGateway::class);
        $this->app->singleton('pay.nowpayments', NowPaymentsGateway::class);
        $this->app->singleton('pay.cryptomus', CryptomusGateway::class);
        $this->app->singleton('pay.coinpayments', CoinPaymentsGateway::class);
        $this->app->singleton('pay.payssion', PayssionGateway::class);

        // Payout account resolution (ROADMAP §Layer 0.1). Paystack first for its
        // markets, Flutterwave as the wider-net resolver. Injected as a list so
        // tests can drive the service with fakes.
        $this->app->singleton(PayoutAccountService::class, fn ($app) => new PayoutAccountService([
            $app->make(PaystackBankResolver::class),
            $app->make(FlutterwaveBankResolver::class),
        ]));

        // Payout (money-out) gateways, resolved by name via app("payout.$provider"),
        // and the engine that owns the withdrawal lifecycle (ROADMAP §Layer 0.2).
        $this->app->singleton('payout.paystack', PaystackPayoutGateway::class);
        $this->app->singleton('payout.flutterwave', FlutterwavePayoutGateway::class);
        $this->app->singleton('payout.paypal', PayPalPayoutGateway::class);
        $this->app->singleton('payout.cryptomus', CryptomusPayoutGateway::class);
        $this->app->singleton('payout.stripe', StripePayoutGateway::class);
        $this->app->singleton(PayoutService::class, fn ($app) => new PayoutService([
            $app->make(PaystackPayoutGateway::class),
            $app->make(FlutterwavePayoutGateway::class),
            $app->make(PayPalPayoutGateway::class),      // international → PayPal email
            $app->make(CryptomusPayoutGateway::class),   // crypto payout rail
            $app->make(StripePayoutGateway::class),      // Stripe Connect transfer
        ]));

        // KYC/identity providers, resolved by name via app("kyc.$provider"), and
        // the service that owns verification state (ROADMAP §Layer 0.3). Manual
        // review is the always-available fallback.
        $this->app->singleton('kyc.manual', ManualKycProvider::class);
        $this->app->singleton('kyc.smileid', SmileIdKycProvider::class);
        $this->app->singleton('kyc.dojah', DojahKycProvider::class);
        $this->app->singleton(KycService::class, fn ($app) => new KycService([
            $app->make(ManualKycProvider::class),
            $app->make(SmileIdKycProvider::class),
            $app->make(DojahKycProvider::class),
        ]));

        // Claude-assisted maintenance loop (blueprint Section 29). Bound to the
        // production clients by default; both are gated on config and report
        // unavailable until configured. Tests swap in fakes.
        $this->app->bind(
            FixProposer::class,
            ClaudeFixProposer::class,
        );
        $this->app->bind(
            CodeHostClient::class,
            GitHubCodeHostClient::class,
        );

        // NaaraCare AI support agent (Module 24). Prod impl calls the Anthropic
        // Messages API with tool-use, gated on the key; tests inject a fake.
        $this->app->bind(
            ChatModel::class,
            ClaudeChatModel::class,
        );

        // Support voice (Module 25) — ElevenLabs in prod; faked in tests.
        $this->app->bind(
            VoiceSynthesizer::class,
            ElevenLabsVoice::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // White-label brand token (@brand). Echoes text with the shipped 'Naara'
        // token swapped for the admin-set brand word — used for the handful of
        // brand names generated/shown centrally. A no-op on a default install.
        Blade::directive('brand', function ($expr) {
            $expr = $expr === '' ? "'".BrandSettings::DEFAULT_WORD."'" : $expr;

            return "<?php echo e(\\App\\Support\\BrandSettings::rebrand($expr)); ?>";
        });

        // Force HTTPS URL generation in production (NAARA-BUILD-20 §1). A second,
        // complementary safeguard to trustProxies for the signed-URL 403 class:
        // even if forwarded-header detection is incomplete for a given host,
        // every generated URL (verification, reset) still carries https. Gated to
        // production so local dev over plain HTTP is unaffected.
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Livewire stores EVERY file upload to a temporary disk before any app
        // code runs, defaulting to filesystems.default. With FILESYSTEM_DISK set
        // to an unconfigured Wasabi, that temp store throws before MediaStorage's
        // Wasabi-or-local fallback can help — silently breaking every upload
        // sitewide (KYC docs, avatars, chat attachments, admin art). Pin the temp
        // disk to the SAME resolution MediaStorage uses, evaluated live at boot so
        // it keeps working with zero Wasabi keys and upgrades automatically once
        // keys are present. (Not a static .env value — that's the footgun.)
        config(['livewire.temporary_file_upload.disk' => MediaStorage::disk()]);

        // Production misconfiguration guard (BUILD-1 §2.2 / §3.8): if the queue is
        // sync or debug is on in production, log it loudly once per boot. The same
        // warnings render as a banner on the admin dashboard so a non-technical
        // operator actually sees them (EnvironmentGuard).
        foreach (EnvironmentGuard::warnings() as $w) {
            Log::warning('[env-guard] '.$w['title'].' — '.$w['detail']);
        }

        // Blueprint Section 3.1: super_admin bypasses every authorization gate.
        // Admins can only assign roles below their own (enforced per-action later).
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Preloader Studio (BLUEPRINT-preloader-studio §4): feature modules teach
        // the domain-agnostic preloader system about their own page types here,
        // rather than the system hardcoding anything feature-specific. Idempotent
        // and DB-guarded, so it's a no-op on a not-yet-migrated install.
        PreloaderSettings::registerPageType('numbers', 'Numbers & Virtual Lines');
        PreloaderSettings::registerPageType('gifts', 'Naara Gift');

        // Self-hosted web-push channel, addressable as 'webpush' in a
        // notification's via() (owner request — closed-tab notifications).
        Notification::extend(
            'webpush',
            fn ($app) => new WebPushChannel,
        );

        // A reversed/failed credit withdrawal returns the held credits
        // (ROADMAP §Layer 1). Registered explicitly so it fires regardless of
        // listener auto-discovery.
        Event::listen(
            PayoutReversed::class,
            ReturnWithdrawnCredits::class,
        );

        // A reversed/failed merchant-earnings withdrawal returns the held
        // earnings to the merchant bucket (ROADMAP §Layer 3.4).
        Event::listen(
            PayoutReversed::class,
            ReturnMerchantEarnings::class,
        );

        // Same, for a reversed partner profit-share payout.
        Event::listen(
            PayoutReversed::class,
            ReturnPartnerEarnings::class,
        );

        // Same, for a reversed platform-earnings (white-label license sale
        // proceeds) withdrawal — Prompt 21-EXT §5.4.
        Event::listen(
            PayoutReversed::class,
            ReturnPlatformEarnings::class,
        );

        // Same, for a reversed referral margin-share payout (BUILD-22).
        Event::listen(
            PayoutReversed::class,
            ReturnReferralEarnings::class,
        );

        // Same, for a reversed staff profit-share payout (BUILD-23).
        Event::listen(
            PayoutReversed::class,
            ReturnStaffEarnings::class,
        );

        // Track every login's device fingerprint and alert on a genuinely new
        // one (Sept-14 owner request — 2FA/device-login alerts).
        Event::listen(Login::class, RecordLoginDevice::class);

        // HOTFIX §2: record every scheduled task's last successful run, so the
        // admin System Health panel can show whether the live cron is actually
        // firing (the confirmed root cause behind "payment didn't credit" and
        // "provider health widget is empty").
        Event::listen(function (ScheduledTaskFinished $event) {
            SchedulerHealth::record((string) $event->task->command);
        });

        // NAARA-BUILD-16 — NCI (Layer 3) subscribes to the routing/health signals,
        // ALWAYS via ShouldQueue listeners, so learning never runs on the customer
        // request path. NCI reads outcomes and writes only its own registry columns.
        Event::listen(ProviderOutcomeRecorded::class, IncrementNciScore::class);
        Event::listen(CircuitOpened::class, RecomputeProviderScore::class);
        Event::listen(CircuitClosed::class, RecomputeProviderScore::class);
        Event::listen(HealthCheckCompleted::class, RefreshNciOnHealthCheck::class);

        // Extend Socialite with the extra sign-in providers (owner request).
        // Google/Facebook/Twitter are core drivers; Apple/Microsoft/Discord are
        // registered here via their SocialiteProviders packages.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', Provider::class);
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
        });

        // Overlay any admin-saved API credentials on top of config() so every
        // service keeps reading config('services.*') unchanged and providers
        // flip Active the moment a key is saved (blueprint Section 17.4, money
        // rule 10). Runs every request/job; degrades to .env pre-install.
        ProviderKeys::applyToConfig();

        // Per-gateway sandbox/live mode (BUILD-2 §3): swap the base URL for the
        // gateways whose sandbox and live API are on different hosts (PayPal,
        // NOWPayments). Runs after ProviderKeys so the mode host is authoritative.
        PaymentGatewayConfig::applyToConfig();

        // Dual sandbox/live gateway keys (HOTFIX §4): overlay the mode-appropriate
        // stored key for the card gateways. Read-only + additive, so an old-style
        // single key keeps working until the operator populates the new fields.
        // (The one-time migration from a legacy key runs when the admin opens the
        // Gateways page — never at boot, which can precede the settings table.)
        GatewayCredentials::applyToConfig();

        // Same overlay for admin-managed outgoing-mail config (Module 22): the
        // operator sets the mailer + SMTP creds + "from" identity in the panel,
        // and every Mailable/Notification picks them up with no .env editing.
        MailSettings::applyToConfig();

        // Custom-icon overrides are cached; bust that cache when the mapping
        // setting changes (blueprint Section 16.3).
        Setting::saved(function (Setting $setting) {
            if ($setting->key === 'ui.icon_overrides') {
                IconOverrides::flush();
            }
            if (SplashSettings::isSplashKey($setting->key)) {
                SplashSettings::flush();
            }
            if (SecuritySettings::isSecurityKey($setting->key)) {
                SecuritySettings::flush();
            }
            if (ProviderKeys::isProviderKey($setting->key)) {
                ProviderKeys::flush();
            }
            if (MailSettings::isMailKey($setting->key)) {
                MailSettings::flush();
            }
            if (SupportSettings::isSupportKey($setting->key)) {
                SupportSettings::flush();
            }
            if (SupportAutopilot::isAutopilotKey($setting->key)) {
                SupportAutopilot::flush();
            }
            if (BrandSettings::isBrandKey($setting->key)) {
                BrandSettings::flush();
            }
            if (BentoIcons::isBentoKey($setting->key)) {
                BentoIcons::flush();
            }
            if (TaxRates::isTaxKey($setting->key)) {
                TaxRates::flush();
            }
            if (HeroBackground::isHeroKey($setting->key)) {
                HeroBackground::flush();
            }
            if (FeatureFlags::isFeatureKey($setting->key)) {
                FeatureFlags::flush();
            }
            if (EsimHeroContent::isHeroKey($setting->key)) {
                EsimHeroContent::flush();
            }
            if (NumbersHeroContent::isHeroKey($setting->key)) {
                NumbersHeroContent::flush();
            }
            if (GiftHeroBackground::isHeroKey($setting->key)) {
                GiftHeroBackground::flush();
            }
            if (SiteContent::isSiteKey($setting->key)) {
                SiteContent::flush();
            }
            if (NumberCatalogue::isCatalogueKey($setting->key)) {
                NumberCatalogue::flush();
            }
            if (ServiceIcons::isServiceIconKey($setting->key)) {
                ServiceIcons::flush();
            }
            if (SiteChrome::isChromeKey($setting->key)) {
                SiteChrome::flush();
            }
            if (LegalContent::isLegalKey($setting->key)) {
                LegalContent::flush();
            }
            if (CreditSettings::isCreditKey($setting->key)) {
                CreditSettings::flush();
            }
        });

        // Banner cache follows the Banner model itself (Module 31).
        NumbersBentoCard::saved(fn () => NumbersBento::flush());
        NumbersBentoCard::deleted(fn () => NumbersBento::flush());

        Banner::saved(fn () => Banners::flush());
        Banner::deleted(fn () => Banners::flush());

        // Rate limits (blueprint Section 19.2): 300/min authenticated, 60/min
        // public; 10/min for order actions (enforced in the checkout components).
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)
            ->by(optional($request->user())->id ?: $request->ip()));

        // Admin area (blueprint Section 25): per-admin (or per-IP) cap to blunt
        // brute-force probing of the secret admin path.
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(config('admin.throttle', 60))
            ->by('admin:'.(optional($request->user())->id ?: $request->ip())));
    }
}
