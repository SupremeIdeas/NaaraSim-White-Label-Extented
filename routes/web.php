<?php

use App\Http\Controllers\AccountExportController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CustomPageController;
use App\Http\Controllers\DeveloperDocsController;
use App\Http\Controllers\DownloadAppController;
use App\Http\Controllers\EsimQrController;
use App\Http\Controllers\GiftCardOrderController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SupportAttachmentController;
use App\Http\Controllers\SupportVoiceController;
use App\Http\Controllers\VoiceTokenController;
use App\Http\Controllers\Webhooks\AppBuildWebhookController;
use App\Http\Controllers\Webhooks\GetatextWebhookController;
use App\Http\Controllers\Webhooks\GiftCardWebhookController;
use App\Http\Controllers\Webhooks\KycWebhookController;
use App\Http\Controllers\Webhooks\OfferwallPostbackController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\PayoutWebhookController;
use App\Http\Controllers\Webhooks\SmsInboundWebhookController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Http\Controllers\Webhooks\TwilioDialerWebhookController;
use App\Http\Controllers\Webhooks\TwilioDialStatusWebhookController;
use App\Http\Controllers\Webhooks\TwilioRecordingWebhookController;
use App\Http\Controllers\Webhooks\TwilioVoiceWebhookController;
use App\Http\Controllers\VoicemailAudioController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Livewire\Account;
use App\Livewire\Admin\AccountDeletions;
use App\Livewire\Admin\LegalHoldRecords;
use App\Livewire\Admin\Alerts;
use App\Livewire\Admin\Analytics;
use App\Livewire\Admin\Announcements;
use App\Livewire\Admin\AppBuilder;
use App\Livewire\Admin\Backups;
use App\Livewire\Admin\Banners;
use App\Livewire\Admin\BentoIcons;
use App\Livewire\Admin\BrandDirectory;
use App\Livewire\Admin\Branding;
use App\Livewire\Admin\LinkPreviews;
use App\Livewire\Admin\Coupons;
use App\Livewire\Admin\Credits;
use App\Livewire\Admin\CustomPages;
use App\Livewire\Admin\DeveloperApi;
use App\Livewire\Admin\EmailBroadcast;
use App\Livewire\Admin\EmailSettings;
use App\Livewire\Admin\EmailStudio;
use App\Livewire\Admin\ErrorLogViewer;
use App\Livewire\Admin\EsimControlCenter;
use App\Livewire\Admin\EsimHero;
use App\Livewire\Admin\ExchangeRates;
use App\Livewire\Admin\Features;
use App\Livewire\Admin\Gateways;
use App\Livewire\Admin\GiftHero;
use App\Livewire\Admin\Guides;
use App\Livewire\Admin\HomeMedia;
use App\Livewire\Admin\Incidents;
use App\Livewire\Admin\Integrations;
use App\Livewire\Admin\JourneyGoals;
use App\Livewire\Admin\KycReview;
use App\Livewire\Admin\LegalEditor;
use App\Livewire\Admin\Maintenance;
use App\Livewire\Admin\WhiteLabelUpdater;
use App\Livewire\Admin\MarketingCopyStudio;
use App\Livewire\Admin\Merchants;
use App\Livewire\Admin\NavSettings;
use App\Livewire\Admin\NavSlots;
use App\Livewire\Admin\Nci\HealthMonitor;
use App\Livewire\Admin\Nci\ProviderDetail;
use App\Livewire\Admin\Nci\ProviderRegistry;
use App\Livewire\Admin\Nci\RoutingConsole;
use App\Livewire\Admin\Nci\Wallets;
use App\Livewire\Admin\NumbersBento;
use App\Livewire\Admin\NumbersHero;
use App\Livewire\Admin\PageBuilder;
use App\Livewire\Admin\Partners;
use App\Livewire\Admin\Payouts;
use App\Livewire\Admin\PlatformThemePage;
use App\Livewire\Admin\Posts;
use App\Livewire\Admin\PreloaderStudio;
use App\Livewire\Admin\ThemeToggleStudio;
use App\Livewire\Admin\Pricing;
use App\Livewire\Admin\PricingArchitect;
use App\Livewire\Admin\ProductLines;
use App\Livewire\Admin\ProviderKeys;
use App\Livewire\Admin\Reconciliation;
use App\Livewire\Admin\RecoverPassword;
use App\Livewire\Admin\Refunds;
use App\Livewire\Admin\Security;
use App\Livewire\Admin\ServiceIconsPage;
use App\Livewire\Admin\SidebarMenu;
use App\Livewire\Admin\SiteChromePage;
use App\Livewire\Admin\SiteEditor;
use App\Livewire\Admin\SocialHunt;
use App\Livewire\Admin\Splash;
use App\Livewire\Admin\Staff;
use App\Livewire\Admin\SupportAgent;
use App\Livewire\Admin\SupportQueue;
use App\Livewire\Admin\SystemHealth;
use App\Livewire\Admin\TaxRates;
use App\Livewire\Admin\ThemePicker;
use App\Livewire\Admin\UiKit;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\WelcomeSettings;
use App\Livewire\BecomeMerchant;
use App\Livewire\Blog;
use App\Livewire\BrandHunt;
use App\Livewire\BrandManage;
use App\Livewire\CallForwarding;
use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Livewire\Contacts;
use App\Livewire\Dashboard;
use App\Livewire\DataEstimator;
use App\Livewire\DeveloperPortal;
use App\Livewire\Dialer;
use App\Livewire\GetListed;
use App\Livewire\GetNumber;
use App\Livewire\GiftCards;
use App\Livewire\Guide;
use App\Livewire\IdentityVerification;
use App\Livewire\Journey;
use App\Livewire\MerchantClients;
use App\Livewire\MerchantDashboard;
use App\Livewire\MerchantEarnings;
use App\Livewire\MerchantInvoices;
use App\Livewire\MerchantJoin;
use App\Livewire\Messages;
use App\Livewire\MyLines;
use App\Livewire\PortIn;
use App\Livewire\Admin\PortInRequests;
use App\Livewire\Notifications;
use App\Livewire\PartnerEarnings;
use App\Livewire\PricingPage;
use App\Livewire\Profile;
use App\Livewire\Receipts;
use App\Livewire\Referrals;
use App\Livewire\Rewards;
use App\Livewire\SecurityCenter;
use App\Livewire\StatusPage;
use App\Livewire\SupportChat;
use App\Livewire\Wallet;
use App\Livewire\WelcomeAurora;
use App\Livewire\Withdraw;
use App\Support\LegalContent;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Route;

// Public marketing site (Module 27) — CMS-driven pages (SiteContent), edited
// from Admin → Pages with no redeploy.
Route::get('/', fn () => view('marketing.home', ['sections' => SiteContent::page('home')]))->name('home');
Route::get('/about', fn () => view('marketing.about', ['sections' => SiteContent::page('about')]))->name('about');
Route::get('/how-it-works', fn () => view('marketing.how-it-works', ['sections' => SiteContent::page('how-it-works')]))->name('how-it-works');
Route::get('/contact', fn () => view('marketing.contact', ['sections' => SiteContent::page('contact')]))->name('contact');

// Public Developer API documentation (ROADMAP §Layer 2) — renders the canonical
// docs/DEVELOPER-API.md reference as a browsable, branded page.
Route::get('/developers', DeveloperDocsController::class)->name('developers');

// Admin-authored custom-HTML pages (CMS). Prefixed to /p/ so it can never shadow
// a built-in route; only published, non-reserved slugs resolve.
Route::get('/p/{slug}', CustomPageController::class)
    ->where('slug', '[a-z0-9-]+')->name('custom-page');

// Web installer (blueprint Section 22.1). Active only until the lock file
// exists (EnsureNotInstalled).
Route::middleware('installer')->prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'welcome']);
    Route::get('/requirements', [InstallController::class, 'requirements']);
    Route::get('/setup', [InstallController::class, 'setup']);
    Route::post('/setup', [InstallController::class, 'install'])->name('install.run');
});

// Social login (Module 23). Guarded internally by SocialLogin::googleEnabled().
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('social.callback');

// Public legal/help pages (blueprint Section 32; Module 30 legal CMS).
Route::get('/legal', fn () => view('legal.index', ['docs' => LegalContent::all()]))->name('legal');
Route::get('/legal/{slug}', function (string $slug) {
    abort_unless(LegalContent::exists($slug), 404);

    return view('legal.show', ['doc' => LegalContent::doc($slug)]);
})->name('legal.show');
Route::get('/refund-policy', fn () => view('legal.show', ['doc' => LegalContent::doc('refund')]))->name('refund-policy');
// FAQ — wired the same way as About/How It Works/Contact: a SiteContent-driven
// page (admin-editable hero incl. background image via Admin → Pages → FAQ)
// whose Q&A content is pulled live from the homepage's own 'faq' section, so
// there is exactly one FAQ list on the whole site, never two copies to drift.
Route::get('/faq', fn () => view('marketing.faq', ['sections' => SiteContent::page('faq')]))->name('faq');

// Installable app: dynamic PWA manifest + public "Download the App" page.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('/download', DownloadAppController::class)->name('download');
Route::view('/offline', 'pages.offline')->name('offline');
// First-run onboarding carousel (installed app) → lands on login.
Route::get('/get-started', OnboardingController::class)->name('onboarding');

// Public, unauthenticated system status page (for users + Developer API integrators).
Route::get('/status', StatusPage::class)->name('status');

// Public blog (Module 30 · Blog overhaul). Index is a Livewire component so the
// SAME hero/carousel/infinite-feed serves marketing + the in-app floating nav.
Route::get('/blog', Blog::class)->name('blog');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// Public pricing page (Module 29) — real plans when live, estimate tiers before.
Route::get('/pricing', PricingPage::class)->name('pricing');

// Public, unauthenticated invoice view (Merchant V2 invoicing) — the link a
// merchant forwards to a client, who never has a NaaraSim login.
Route::get('/i/{token}', [PublicInvoiceController::class, 'show'])->name('invoice.public');

// Merchant invite landing (ROADMAP §Layer 3.3) — a reseller's co-branded
// storefront link. Captures the invite and sends the visitor to register.
Route::get('/merchant/{slug}/join', MerchantJoin::class)->name('merchant.join');

// Authenticated customer app (blueprint Sections 4, 12, 14, 16). `active`
// confines a self-paused account to the account page until it reactivates
// (Section 26.1).
Route::middleware(['auth', 'active'])->group(function () {
    // Money + core app routes require a verified email — but only once outgoing
    // mail is configured (owner request), so users are never trapped behind a
    // verification link that can't be sent yet.
    Route::middleware('verified.mail')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/catalogue', Catalogue::class)->name('catalogue');
        // Naara Gift storefront (feature-gated: 404 until naara_gift is live).
        Route::get('/gift-cards', GiftCards::class)->name('gift-cards');
        Route::get('/gift-cards/orders', [GiftCardOrderController::class, 'index'])->name('gift-cards.orders');
        Route::get('/gift-cards/orders/{order}', [GiftCardOrderController::class, 'show'])->name('gift-cards.order');
        Route::post('/gift-cards/orders/{order}/balance', [GiftCardOrderController::class, 'balance'])->name('gift-cards.order.balance');
        Route::get('/checkout/{plan}', Checkout::class)->name('checkout');
        Route::get('/wallet', Wallet::class)->name('wallet');
        Route::get('/numbers', GetNumber::class)->name('numbers');
        // My Lines — the dedicated management hub for owned eSIMs + numbers
        // (active, grouped by Model, with an Archive). Numbers section home.
        Route::get('/numbers/lines', MyLines::class)->name('numbers.lines');
        // Call forwarding for permanent numbers (Live Voice — Part A). The
        // component 404s unless Twilio is Active (voice rides the same keys).
        Route::get('/numbers/forwarding', CallForwarding::class)->name('numbers.forwarding');
        // In-browser international dialer (Live Voice — Part B). Also 404s until
        // Twilio is Active. The token endpoint mints the short-lived WebRTC token.
        Route::get('/numbers/dialer', Dialer::class)->name('numbers.dialer');
        Route::post('/voice/token', VoiceTokenController::class)->name('voice.token');
        // In-app contact book (Live Voice — Part C). Not provider-billed, so no
        // feature gate — standard auth-scoped CRUD that feeds the dialer.
        Route::get('/numbers/contacts', Contacts::class)->name('numbers.contacts');
        // Conversation inbox (Numbers overhaul §1) — inbound + outbound threads.
        Route::get('/numbers/messages', Messages::class)->name('numbers.messages');
        // Voicemail audio (Prompt 11) — streamed from the private disk, owner-only.
        Route::get('/numbers/voicemail/{message}', VoicemailAudioController::class)->name('numbers.voicemail-audio');
        // Port-in intake (Prompt 11) — bring an existing US/Canada number to Naara.
        // Honest multi-day carrier process, not instant provisioning.
        Route::get('/numbers/port-in', PortIn::class)->name('numbers.port-in');
        Route::get('/referrals', Referrals::class)->name('referrals');

        // NaaraCredits rewards area (loyalty module) — opt-in earning.
        Route::get('/receipts', Receipts::class)->name('receipts');
        Route::get('/rewards', Rewards::class)->name('rewards');
        // My Journey — loyalty milestones + travel/eSIM history, two tabs.
        Route::get('/journey', Journey::class)->name('journey');
        // Brand Partner Hunt (BUILD-6 §C) — follow-to-earn NaaraCredits.
        Route::get('/rewards/hunt', BrandHunt::class)->name('rewards.hunt');
        // Brand Directory self-service (BUILD-9) — get listed + manage a listing.
        Route::get('/brand/get-listed', GetListed::class)->name('brand.get-listed');
        Route::get('/brand/manage', BrandManage::class)->name('brand.manage');

        // Partner profit-share earnings (dollars only; 404 for non-partners).
        Route::get('/partner', PartnerEarnings::class)->name('partner.earnings');

        // Cash out withdrawable (first-referral) credits (ROADMAP §Layer 1).
        // Browsing + payout-account setup are free (NAARA-BUILD-22 §3); KYC-L2
        // is only enforced once the unified free-payout threshold is spent
        // (WithdrawalService::request(), same as every other earner type).
        Route::get('/rewards/withdraw', Withdraw::class)->name('rewards.withdraw');

        // Data estimator (blueprint Section 32).
        Route::get('/data-estimator', DataEstimator::class)->name('data-estimator');

        // Developer portal (ROADMAP §Layer 2) — only when the Developer API is
        // enabled (the api.enabled middleware 404s otherwise, hiding the program).
        Route::get('/developer', DeveloperPortal::class)
            ->middleware('api.enabled')->name('developer');

        // Become a merchant (ROADMAP §Layer 3.1) — KYB + application flow. The
        // page self-gates on the programme flag + KYC L3.
        Route::get('/merchant/apply', BecomeMerchant::class)->name('merchant.apply');
        // Merchant storefront dashboard (ROADMAP §Layer 3.5) — active merchants
        // only (404 otherwise): storefront, invite link, customers, earnings, payouts.
        Route::get('/merchant', MerchantDashboard::class)->name('merchant.dashboard');
        // Merchant earnings analytics (BUILD-7 §3) — reporting over MerchantEarning.
        Route::get('/merchant/earnings', MerchantEarnings::class)->name('merchant.earnings');
        // Merchant V2 — client management (404s for a non-V2 merchant).
        Route::get('/merchant/clients', MerchantClients::class)->name('merchant.clients');
        // Merchant V2 — invoice dashboard (404s for a non-V2 merchant).
        Route::get('/merchant/invoices', MerchantInvoices::class)->name('merchant.invoices');
        // NOTE: self-service white-label license SALES are master-only and were
        // removed here (see docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md) —
        // a white-label instance never resells licenses to sub-merchants.

        // eSIM activation QR (SVG), generated from the LPA string. Owner- or
        // assigning-merchant-scoped inside the controller.
        Route::get('/esim/{order}/qr', EsimQrController::class)->name('esim.qr');
    });

    // Aurora Welcome entrance (first-login animation) — reachable while
    // unverified so it plays immediately after signup, then hands off to the
    // dashboard. Guards itself: no `just_registered` flag → straight to home.
    Route::get('/welcome', WelcomeAurora::class)->name('welcome');

    // In-app user guide + agreement (per-audience: user / merchant / V2 / developer).
    Route::get('/guide', Guide::class)->name('guide');

    // Account & data rights (blueprint Section 26) — reachable while unverified
    // so a user can still manage or delete their account and resend the email.
    Route::get('/account', Account::class)->name('account');
    // Extended self-service profile (owner request).
    Route::get('/account/profile', Profile::class)->name('profile');
    // Staff compensation earnings — the shared payout dashboard (BUILD-23 §4),
    // staff-only. Staff are KYC-exempt for their profit-share withdrawals.
    Route::get('/staff/earnings', fn () => view('staff.earnings'))
        ->middleware('role:staff|super_admin')->name('staff.earnings');
    // Security Center (Module 23) — also reachable unverified (to change email).
    Route::get('/account/security', SecurityCenter::class)->name('security');
    // Identity verification (ROADMAP §Layer 0.3) — KYC L2 gate for withdrawals.
    Route::get('/account/verify', IdentityVerification::class)->name('account.verify');
    // In-app notification centre (owner request) — the bell's "see all" page.
    Route::get('/notifications', Notifications::class)->name('notifications');
    // Self-hosted web-push subscribe/unsubscribe (owner request).
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    // NaaraCare AI support chat (Module 24) — reachable unverified (they may need help).
    Route::get('/support', SupportChat::class)->name('support');
    // Private support voice clips (Module 25) — owner or ticket staff only.
    Route::get('/support/voice/{message}', SupportVoiceController::class)->name('support.voice');
    // Private support evidence attachments — owner or ticket staff only.
    Route::get('/support/attachment/{message}', SupportAttachmentController::class)->name('support.attachment');
    Route::get('/account/export', AccountExportController::class)
        ->name('account.export.download');
});

// Dedicated admin sign-in page (blueprint Section 25). Lives at
// {ADMIN_PATH}/login OUTSIDE the `admin` gate so guests can reach it; EnsureAdmin
// redirects unauthenticated panel visitors here (a branded admin login) instead
// of the customer login. Posts to Fortify /login. Throttled like the panel.
Route::get(config('admin.path').'/login', LoginController::class)
    ->middleware('throttle:admin')->name('admin.login');

// Admin password recovery via security questions (guest, hard-throttled). A
// self-service path when email reset isn't available on a self-hosted install.
Route::get(config('admin.path').'/recover', RecoverPassword::class)
    ->middleware('throttle:admin')->name('admin.recover');

// Admin panel (blueprint Sections 13, 15, 17, 25). Mounted on the env-driven
// admin path; the `admin` middleware enforces the IP allow-list, a redirect to
// the dedicated admin login for guests, a plain 404 for signed-in non-admins,
// and TOTP 2FA enrolment. `auth` is intentionally omitted so EnsureAdmin owns
// the guest handling. Throttled to blunt path probing.
Route::middleware(['admin', 'throttle:admin'])
    ->prefix(config('admin.path'))
    ->name('admin.')
    ->group(function () {
        // Panel entry + 2FA self-enrolment — any panel user (incl. staff).
        Route::get('/', App\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/security', Security::class)->name('security');
        // Personal account (any panel user manages their own name/email/password).
        Route::get('/account', App\Livewire\Admin\Account::class)->name('account');

        // Admin configuration — super_admin & admin only (staff excluded).
        Route::middleware('role:super_admin|admin')->group(function () {
            Route::get('/pricing', Pricing::class)->name('pricing');
            Route::get('/exchange-rates', ExchangeRates::class)->name('exchange-rates');
            Route::get('/pricing/architect', PricingArchitect::class)->name('pricing-architect');
            Route::get('/errors', ErrorLogViewer::class)->name('errors');
            // Feature toggles (owner request) — switch features on/off + setup guides.
            Route::get('/features', Features::class)->name('features');
            // Port-in requests (Prompt 11) — ops workflow for customers bringing
            // a US/Canada number to Naara.
            Route::get('/port-in-requests', PortInRequests::class)->name('port-in-requests');
            // eSIM storefront hero (esim_upgrade Part 2) — title, description, images.
            Route::get('/esim-hero', EsimHero::class)->name('esim-hero');
            Route::get('/numbers-hero', NumbersHero::class)->name('numbers-hero');
            Route::get('/numbers-cards', NumbersBento::class)->name('numbers-cards');
            Route::get('/bento-icons', BentoIcons::class)->name('bento-icons');
            Route::get('/appearance', Splash::class)->name('appearance');
            Route::get('/preloader-studio', PreloaderStudio::class)->name('preloader-studio');
            Route::get('/theme-toggle-studio', ThemeToggleStudio::class)->name('theme-toggle-studio');
            // Marketing Copy Studio — Claude-assisted copy population for CMS pages.
            Route::get('/copy-studio', MarketingCopyStudio::class)->name('copy-studio');
            Route::get('/dashboard-theme', PlatformThemePage::class)->name('dashboard-theme');
            // Theme picker — switch the platform-wide visual skin (Theme Batch 2 §4).
            Route::get('/theme', ThemePicker::class)->name('theme');
            Route::get('/branding', Branding::class)->name('branding');
            Route::get('/link-previews', LinkPreviews::class)->name('link-previews');
            Route::get('/site', SiteEditor::class)->name('site');
            Route::get('/product-lines', ProductLines::class)->name('product-lines');
            Route::get('/email-studio', EmailStudio::class)->name('email-studio');
            Route::get('/email-broadcast', EmailBroadcast::class)->name('email-broadcast');
            Route::get('/home-media', HomeMedia::class)->name('home-media');
            Route::get('/sidebar-menu', SidebarMenu::class)->name('sidebar-menu');
            Route::get('/chrome', SiteChromePage::class)->name('chrome');
            Route::get('/legal', LegalEditor::class)->name('legal');
            Route::get('/blog', Posts::class)->name('blog');
            Route::get('/pages', CustomPages::class)->name('pages');
            Route::get('/builder', PageBuilder::class)->name('builder');
            Route::get('/app-builder', AppBuilder::class)->name('app-builder');
            Route::get('/welcome-settings', WelcomeSettings::class)->name('welcome-settings');
            Route::get('/incidents', Incidents::class)->name('incidents');
            Route::get('/notices', Alerts::class)->name('notices');
            Route::get('/nav', NavSlots::class)->name('nav');
            Route::get('/bottom-nav', NavSettings::class)->name('bottom-nav');
            Route::get('/guides', Guides::class)->name('guides');
            Route::get('/service-icons', ServiceIconsPage::class)->name('service-icons');
            Route::get('/banners', Banners::class)->name('banners');
            Route::get('/coupons', Coupons::class)->name('coupons');
            // Announcements & offers — push to every user's notification bell.
            Route::get('/announcements', Announcements::class)->name('announcements');
            Route::get('/credits', Credits::class)->name('credits');
            Route::get('/journey-goals', JourneyGoals::class)->name('journey-goals');
            Route::get('/social-hunt', SocialHunt::class)->name('social-hunt');
            Route::get('/brand-directory', BrandDirectory::class)->name('brand-directory');
            Route::get('/gift-cards', App\Livewire\Admin\GiftCards::class)->name('gift-cards');
            // Naara Gift storefront hero — same system as the dashboard home hero.
            Route::get('/gift-hero', GiftHero::class)->name('gift-hero');
            Route::get('/developer-api', DeveloperApi::class)->name('developer-api');
            Route::get('/payouts', Payouts::class)->name('payouts');
            Route::get('/refunds', Refunds::class)->name('refunds');
            Route::get('/reconciliation', Reconciliation::class)->name('reconciliation');
            Route::get('/analytics', Analytics::class)->name('analytics');
            Route::get('/tax-rates', TaxRates::class)->name('tax-rates');
            Route::get('/system-health', SystemHealth::class)->name('system-health');
            // NOTE: white-label OVERSIGHT (the registry of all instances) and the
            // project-intake PDF are master-only and were removed here (see
            // docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md). This build keeps
            // only the consumer-side `/updater` screen below.
            Route::get('/gateways', Gateways::class)->name('gateways');
            Route::get('/kyc', KycReview::class)->name('kyc');
            Route::get('/merchants', Merchants::class)->name('merchants');
            Route::get('/partners', Partners::class)->name('partners');
            Route::get('/users', Users::class)->name('users');
            // Growth stack: social links, tracking pixels, social sign-in guides.
            Route::get('/integrations', Integrations::class)->name('integrations');
            Route::get('/deletions', AccountDeletions::class)->name('deletions');
            // Erasure fix Phase A: read-only, heavily-audited lookup of an
            // anonymized account's retained financial/order trail.
            Route::get('/legal-hold', LegalHoldRecords::class)->name('legal-hold');
            Route::get('/support-agent', SupportAgent::class)->name('support-agent');
        });

        // Support ticket queue (Module 25) — staff with the tickets.manage scope,
        // plus admin/super_admin (who hold every scope / bypass).
        Route::middleware('permission:tickets.manage')->group(function () {
            Route::get('/tickets', SupportQueue::class)->name('tickets');
        });

        // eSIM Control Center (BUILD-8 §4) — staff with the esim.manage scope,
        // plus admin/super_admin. One section for sync, margins, tooltips, images.
        Route::middleware('permission:esim.manage')->group(function () {
            Route::get('/esim', EsimControlCenter::class)->name('esim');
        });

        // NCI Operations Center (NAARA-BUILD-17) — staff with the nci.view scope,
        // plus admin/super_admin. Override actions inside each page additionally
        // gate on nci.override.
        Route::middleware('permission:nci.view')->prefix('nci')->name('nci.')->group(function () {
            Route::get('/registry', ProviderRegistry::class)->name('registry');
            Route::get('/provider/{provider}', ProviderDetail::class)->name('provider');
            Route::get('/health', HealthMonitor::class)->name('health');
            Route::get('/routing', RoutingConsole::class)->name('routing');
            Route::get('/wallets', Wallets::class)->name('wallets');
        });

        // Staff, backups + maintenance loop — super_admin only (Sections 27–29).
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/staff', Staff::class)->name('staff');
            Route::get('/api-keys', ProviderKeys::class)->name('api-keys');
            Route::get('/email', EmailSettings::class)->name('email');
            Route::get('/backups', Backups::class)->name('backups');
            Route::get('/maintenance', Maintenance::class)->name('maintenance');
            // Platform updater — WHITE-LABEL SUBSCRIBER screen (Updater Batch 5):
            // pull signed .naaraupdate packages from the original platform, or
            // upload one directly. Apply itself is the same engine + rollback
            // guarantees as Admin\Updater (which this fork keeps but does not
            // route to — the original platform is the publisher-only screen).
            // Most sensitive screen: super_admin.
            Route::get('/updater', WhiteLabelUpdater::class)->name('updater');
            Route::get('/ui-kit', UiKit::class)->name('ui-kit');
        });
    });

// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
// Inbound SMS conversations (Numbers overhaul §1) — verify-before-trust, queued.
Route::post('/webhooks/sms-inbound/{provider}', SmsInboundWebhookController::class)
    ->name('webhooks.sms-inbound');

Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');

// Twilio inbound-call webhook (Live Voice — Part A): signature-verified, returns
// TwiML that forwards the call to the user's configured target.
Route::post('/webhooks/twilio/voice', TwilioVoiceWebhookController::class)
    ->name('webhooks.twilio.voice');

// Voicemail recording webhook (Prompt 11): signature-verified, fires when a
// <Record> started by the voice webhook above finishes.
Route::post('/webhooks/twilio/recording', TwilioRecordingWebhookController::class)
    ->name('webhooks.twilio.recording');

// Twilio in-browser dialer webhooks (Live Voice — Part B): signature-verified.
// `dial` returns the outbound <Dial> TwiML (with a funded timeLimit) for a
// pre-authorised call; `dial-status` settles the wallet on hang-up.
Route::post('/webhooks/twilio/dial', TwilioDialerWebhookController::class)
    ->name('webhooks.twilio.dial');
Route::post('/webhooks/twilio/dial-status', TwilioDialStatusWebhookController::class)
    ->name('webhooks.twilio.dial-status');

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

// Payout (transfer) webhooks (ROADMAP §Layer 0.2): signature-verified,
// idempotent payout-request settlement.
Route::post('/webhooks/payouts/{provider}', PayoutWebhookController::class)
    ->name('webhooks.payouts');

// Stripe Connect account webhooks (ROADMAP §Layer 0.2 — Stripe payout rail):
// a separate endpoint/secret from the above, since it carries account.updated
// onboarding-status events rather than payout-request events.
Route::post('/webhooks/stripe-connect/account', StripeConnectWebhookController::class)
    ->name('webhooks.stripe-connect.account');

// KYC result callbacks (ROADMAP §Layer 0.3): signature-verified, idempotent
// verification decisions.
Route::post('/webhooks/kyc/{provider}', KycWebhookController::class)
    ->name('webhooks.kyc');

// Rewarded-ad / offerwall postback (loyalty module): HMAC-verified,
// idempotent credit grant. Both verbs — networks vary.
Route::match(['get', 'post'], '/webhooks/offerwall', OfferwallPostbackController::class)
    ->name('webhooks.offerwall');

// Native app build status callback (App Export §1): HMAC-verified, flips a
// build queued→building→ready/failed and attaches the artifact + logs.
Route::post('/webhooks/appbuild/{provider}', AppBuildWebhookController::class)
    ->name('webhooks.appbuild');

// Naara Gift async delivery callback (Reloadly/Zendit): HMAC-verified,
// idempotent, fills the three-state receipt so the redemption screen updates.
Route::post('/webhooks/giftcards/{provider}', GiftCardWebhookController::class)
    ->name('webhooks.giftcards');

// WhatsApp Cloud API webhook (WhatsApp Autopilot §7): GET verify handshake +
// X-Hub-Signature-256-verified POST for delivery status and STOP opt-outs.
Route::match(['get', 'post'], '/webhooks/whatsapp', WhatsAppWebhookController::class)
    ->name('webhooks.whatsapp');
