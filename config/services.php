<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // -- eSIM providers (blueprint Sections 5.2-5.4) ----------------------
    // Keys are sandbox-only until Module 12 sign-off. cost/net prices returned
    // by these APIs are PRIVATE and never surfaced to users.

    'esimgo' => [
        'api_key' => env('ESIMGO_API_KEY'),
        'webhook_secret' => env('ESIMGO_WEBHOOK_SECRET'),
        'sandbox' => env('ESIMGO_SANDBOX', false),
        'base_url' => env('ESIMGO_BASE_URL', 'https://api.esim-go.com/v2.5'),
    ],

    'airalo' => [
        'client_id' => env('AIRALO_CLIENT_ID'),
        'client_secret' => env('AIRALO_CLIENT_SECRET'),
        'sandbox' => env('AIRALO_SANDBOX', false),
        // Production: https://partners-api.airalo.com/v2
        // Sandbox:    https://sandbox-partners-api.airalo.com/v2
        'base_url' => env('AIRALO_BASE_URL', 'https://partners-api.airalo.com/v2'),
    ],

    'quibity' => [
        'api_key' => env('QUIBITY_API_KEY'),
        'sandbox' => env('QUIBITY_SANDBOX', false),
        'base_url' => env('QUIBITY_BASE_URL', 'https://esim.sm/api/reseller/v1'),
    ],

    // -- Full-eSIM providers (Naara Connect: calls + data) ----------------
    // Four interchangeable voice+data providers behind the Naara Connect lane.
    // Costs are WHOLESALE and PRIVATE; retail is always ours via PricingEngine.

    // Zendit — also serves plain data eSIMs (data-lane backup). Bearer auth;
    // sandbox and production are DIFFERENT hosts (base_url follows the flag so a
    // test key never hits the live wallet).
    'zendit' => [
        'api_key' => env('ZENDIT_API_KEY'),
        'sandbox' => env('ZENDIT_SANDBOX', false),
        'base_url' => env('ZENDIT_BASE_URL', env('ZENDIT_SANDBOX', false)
            ? 'https://test-api.zendit.io/v1'
            : 'https://api.zendit.io/v1'),
        'webhook_secret' => env('ZENDIT_WEBHOOK_SECRET'),
    ],

    // 1GLOBAL (Connect API) — OAuth2 client-credentials. Partner-access onboarding.
    'oneglobal' => [
        'client_id' => env('ONEGLOBAL_CLIENT_ID'),
        'client_secret' => env('ONEGLOBAL_CLIENT_SECRET'),
        'sandbox' => env('ONEGLOBAL_SANDBOX', false),
        'base_url' => env('ONEGLOBAL_BASE_URL', 'https://api.connect.1global.com/v1'),
    ],

    // Monty Mobile (RSP API) — Bearer API key. Sales-led onboarding.
    'montymobile' => [
        'api_key' => env('MONTYMOBILE_API_KEY'),
        'sandbox' => env('MONTYMOBILE_SANDBOX', false),
        'base_url' => env('MONTYMOBILE_BASE_URL', 'https://rsp.montymobile.com'),
    ],

    // Gigs (Connectivity API) — Bearer API key, project-scoped. Full MVNO stack.
    'gigs' => [
        'api_key' => env('GIGS_API_KEY'),
        'project' => env('GIGS_PROJECT'),
        'sandbox' => env('GIGS_SANDBOX', false),
        'base_url' => env('GIGS_BASE_URL', 'https://api.gigs.com/v1'),
    ],

    // -- Number / SMS providers (blueprint Sections 8-11) -----------------
    // Costs from these APIs are PRIVATE. Never cross lanes (country+type).

    'getatext' => [
        'api_key' => env('GETATEXT_API_KEY'),
        'webhook_url' => env('GETATEXT_WEBHOOK_URL'),
        'webhook_token' => env('GETATEXT_WEBHOOK_TOKEN'), // optional shared secret
        'base_url' => env('GETATEXT_BASE_URL', 'https://getatext.com/api/v1'),
    ],

    'fivesim' => [
        'api_key' => env('FIVESIM_API_KEY'), // Bearer JWT
        'base_url' => env('FIVESIM_BASE_URL', 'https://5sim.net/v1'),
    ],

    // HeroSMS — official SMS-Activate successor (SMS-Activate shut down
    // 2025-12-29). PRIMARY "full rent" provider. Protocol-compatible
    // handler_api.php; api_key passed as a query param.
    //
    // country_map is no longer hand-filled here: `numbers:catalogue-sync`
    // live-discovers it from HeroSMS's own `getCountries` action and name-
    // matches it against NumberCatalogue (HeroSmsService::syncCatalogue(),
    // owner audit, 2026-09-15) — accurate, not guessed, and it self-updates
    // on every re-sync. This array stays as the MANUAL fallback for a
    // country the auto-match ever misses; leave a slug unmapped and it
    // passes through unchanged, which fails safely (out-of-stock) rather
    // than silently hitting the wrong country.
    //
    // service_map has no live-discovery path (the protocol has no named
    // service-list action) — these 5 are the SMS-Activate-standard short
    // codes long established across every known clone of this protocol, so
    // they're safe to ship. Everything else is intentionally left
    // unmapped — the "go-live" step for the rest is checking HeroSMS's own
    // current service dictionary before adding entries here, NOT guessing:
    // a wrong-but-valid code would silently route to the WRONG real
    // service, unlike an unmapped one, which just fails safely.
    // OPS: HeroSMS funds via CRYPTO ONLY — surface before go-live.
    'herosms' => [
        'api_key' => env('HEROSMS_API_KEY'),
        'base_url' => env('HEROSMS_BASE_URL', 'https://hero-sms.com/stubs/handler_api.php'),
        'country_map' => [],
        'service_map' => [
            'whatsapp' => 'wa',
            'telegram' => 'tg',
            'google' => 'go',
            'facebook' => 'fb',
            'instagram' => 'ig',
        ],
    ],

    // VirtSMS — SMS-Activate-protocol fallback behind HeroSMS in the same
    // lane; same maps, same caveats (see herosms above).
    'virtsms' => [
        'api_key' => env('VIRTSMS_API_KEY'),
        'base_url' => env('VIRTSMS_BASE_URL', 'https://virtsms.io/stubs/handler_api.php'),
        'country_map' => [],
        'service_map' => [
            'whatsapp' => 'wa',
            'telegram' => 'tg',
            'google' => 'go',
            'facebook' => 'fb',
            'instagram' => 'ig',
        ],
    ],

    'telnyx' => [
        'api_key' => env('TELNYX_API_KEY'),
        'base_url' => env('TELNYX_BASE_URL', 'https://api.telnyx.com/v2'),
    ],

    // WhatsApp Cloud API (Meta) — WhatsApp Autopilot (BUILD-4 §7). Automated,
    // template-based lifecycle notifications (order delivered, renewal reminder,
    // …). phone_number_id + access_token are required to be Active; app_secret
    // verifies inbound webhooks (X-Hub-Signature-256); verify_token answers the
    // webhook GET handshake. All blank by default → the feature is Coming Soon.
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
        'default_lang' => env('WHATSAPP_DEFAULT_LANG', 'en'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com/2010-04-01'),
        // In-browser dialer (Live Voice — Part B). A standalone API Key (NOT the
        // auth token) signs the short-lived WebRTC access tokens; the TwiML App
        // owns the outbound-call webhook. caller_id is the verified NaaraSim
        // number shown to the party being dialled. default_voice_cost is the
        // per-minute wholesale fallback when the live Pricing API is unreachable.
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        'twiml_app_sid' => env('TWILIO_TWIML_APP_SID'),
        'caller_id' => env('TWILIO_CALLER_ID'),
        'default_voice_cost' => env('TWILIO_DEFAULT_VOICE_COST', 0.02),
        'default_monthly_cost' => env('TWILIO_DEFAULT_MONTHLY_COST', 1.15),
    ],

    // -- Payment gateways (blueprint Sections 14.2 & 19.3) ----------------
    // Sandbox keys only until Module 12. Webhooks are signature-verified.

    'flutterwave' => [
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'), // client-side (Flutterwave modal)
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'), // verif-hash header
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'), // also signs webhooks (HMAC-SHA512)
        'public_key' => env('PAYSTACK_PUBLIC_KEY'), // client-side (Paystack Inline)
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    // PayPal (Orders v2). client_id/secret gate it Active; webhook_id verifies
    // inbound webhooks via PayPal's verify-webhook-signature API.
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
    ],

    // Binance Pay (merchant API v3, crypto rail). HMAC-SHA512 signed requests.
    'binance' => [
        'api_key' => env('BINANCE_PAY_API_KEY'),
        'api_secret' => env('BINANCE_PAY_API_SECRET'),
        'base_url' => env('BINANCE_PAY_BASE_URL', 'https://bpay.binanceapi.com'),
    ],

    // NOWPayments (crypto). api_key gates it; ipn_secret verifies webhooks.
    'nowpayments' => [
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'base_url' => env('NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io'),
    ],

    // Cryptomus (crypto). merchant_id + api_key sign every request + webhook.
    // payout_api_key is a SEPARATE key for the money-out (Payout) API
    // (NAARA-BUILD-22 §5) — blank leaves crypto payouts unavailable.
    'cryptomus' => [
        'merchant_id' => env('CRYPTOMUS_MERCHANT_ID'),
        'api_key' => env('CRYPTOMUS_API_KEY'),
        'payout_api_key' => env('CRYPTOMUS_PAYOUT_API_KEY'),
        'base_url' => env('CRYPTOMUS_BASE_URL', 'https://api.cryptomus.com'),
    ],

    // CoinPayments (crypto). public/private keys sign requests; ipn_secret +
    // merchant_id verify IPNs. pay_currency is the coin the buyer pays in.
    'coinpayments' => [
        'public_key' => env('COINPAYMENTS_PUBLIC_KEY'),
        'private_key' => env('COINPAYMENTS_PRIVATE_KEY'),
        'ipn_secret' => env('COINPAYMENTS_IPN_SECRET'),
        'merchant_id' => env('COINPAYMENTS_MERCHANT_ID'),
        'pay_currency' => env('COINPAYMENTS_PAY_CURRENCY', 'USDT.TRC20'),
    ],

    // Payssion (local payment methods). api_key + secret_key sign + verify.
    'payssion' => [
        'api_key' => env('PAYSSION_API_KEY'),
        'secret_key' => env('PAYSSION_SECRET_KEY'),
        'pm_id' => env('PAYSSION_PM_ID', 'alipay_cn'),
        'base_url' => env('PAYSSION_BASE_URL', 'https://www.payssion.com'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'public_key' => env('STRIPE_PUBLIC_KEY'), // publishable key (Stripe Elements)
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
        // Stripe Connect (payout onboarding — separate webhook endpoint/secret
        // in the Stripe dashboard, since it carries account.updated events
        // rather than checkout/payment events).
        'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
    ],

    // Identity / KYC providers (ROADMAP §Layer 0.3). Admin-managed via the
    // API-keys page; a provider is used only once its keys are present, else
    // NaaraSim falls back to manual admin review.
    'smileid' => [
        'partner_id' => env('SMILEID_PARTNER_ID'),
        'api_key' => env('SMILEID_API_KEY'),
        'base_url' => env('SMILEID_BASE_URL', 'https://api.smileidentity.com'),
    ],

    'dojah' => [
        'app_id' => env('DOJAH_APP_ID'),
        'api_key' => env('DOJAH_API_KEY'),
        'base_url' => env('DOJAH_BASE_URL', 'https://api.dojah.io'),
    ],

    // Social login (Module 23). Keys are admin-managed via the API-keys page;
    // redirect defaults to our callback route on the current APP_URL.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Additional social sign-in providers (owner request). Each lights up only
    // once both credentials are saved; the callback route defaults per provider.
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],
    'twitter' => [ // X (OAuth 2.0)
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI', '/auth/twitter/callback'),
    ],
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', '/auth/apple/callback'),
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),
    ],
    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
    ],

    // Voice replies for support (Module 25). Admin-managed via the API-keys page.
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model' => env('ELEVENLABS_MODEL', 'eleven_v3'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
    ],

    // Claude-assisted maintenance loop (blueprint Section 29) + the AI Pricing
    // Architect (Plan Price with Claude). Both light up only when the key is set.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    // Fine-grained GitHub token scoped to THIS repo only, for opening
    // CI-gated maintenance PRs. Store encrypted at rest.
    'github_maintenance' => [
        'token' => env('GITHUB_MAINTENANCE_TOKEN'),
        'repo' => env('GITHUB_MAINTENANCE_REPO'), // owner/name
        'base_branch' => env('GITHUB_MAINTENANCE_BASE_BRANCH', 'main'),
    ],

    // Cloudflare Turnstile bot protection (blueprint Section 33).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    // Rewarded-ad / offerwall network for the NaaraCredits rewards area (loyalty
    // module). Rewards are granted ONLY via the network's server-to-server
    // postback, HMAC-verified with this secret — never self-reported by the
    // browser. Use a compliant rewarded/offerwall provider (AdGate, AdGem,
    // BitLabs, CPX, etc.), NOT AdSense (which forbids incentivised views).
    'offerwall' => [
        'postback_secret' => env('OFFERWALL_POSTBACK_SECRET'),
    ],

    // Native app-export CI / cloud-build service (App Export §1). The trigger
    // signs its outbound payload with this secret and the status callback is
    // verified against it (hash_equals) — a callback is hard-rejected with 401
    // whenever this is blank, never accepted unsigned (owner audit, 2026-09-15).
    // The build-trigger webhook URL itself is admin-editable
    // (Setting: appexport.ci_webhook_url) for the generic/custom-CI path.
    'appexport' => [
        'ci_secret' => env('APPEXPORT_CI_SECRET'),
        // Codemagic REST API token (x-auth-token header) — owner audit,
        // 2026-09-15: verified against Codemagic's own public docs
        // (docs.codemagic.io/rest-api/builds/). Only used when the admin picks
        // "Codemagic" as the CI provider in App Builder; appId/workflowId/
        // branch are plain (non-secret) Settings, not config, since they're
        // just identifiers, not credentials.
        'codemagic_api_token' => env('CODEMAGIC_API_TOKEN'),
        // GitHub PAT used ONLY to fire the repo's own pre-built
        // android-build.yml via repository_dispatch (docs/APP-EXPORT.md §
        // "Android CI") — owner audit, 2026-09-15 (Doc B Stage 1): Android
        // builds via GitHub Actions (cheap, no macOS runner needed), NOT
        // Codemagic. Least-privilege: a fine-grained PAT scoped to this one
        // repo with "Contents: read" + "Actions: write" is enough — it never
        // touches code, secrets, or other repos.
        'github_token' => env('APPEXPORT_GITHUB_TOKEN'),
    ],

    // Reloadly Gift Cards (Naara Gift — primary gift-card provider). OAuth2
    // client-credentials; the gift-card API is a separate audience/host from
    // Reloadly's other products. Sandbox by default (real keys go in last).
    'reloadly' => [
        'client_id' => env('RELOADLY_CLIENT_ID'),
        'client_secret' => env('RELOADLY_CLIENT_SECRET'),
        'sandbox' => env('RELOADLY_SANDBOX', true),
        'auth_url' => env('RELOADLY_AUTH_URL', 'https://auth.reloadly.com/oauth/token'),
        'webhook_secret' => env('RELOADLY_WEBHOOK_SECRET'),
    ],

    // ── NAARA-BUILD-18: Provider Expansion. All ship enabled=false; keys go in
    //    when Frank actually onboards each provider. Base URLs are the providers'
    //    documented API hosts.
    'smspool' => [
        'api_key' => env('SMSPOOL_API_KEY'),
        'base_url' => env('SMSPOOL_BASE_URL', 'https://api.smspool.net'),
    ],
    'onlinesim' => [
        'api_key' => env('ONLINESIM_API_KEY'),
        'base_url' => env('ONLINESIM_BASE_URL', 'https://onlinesim.io/api'),
    ],
    'plivo' => [
        'auth_id' => env('PLIVO_AUTH_ID'),
        'auth_token' => env('PLIVO_AUTH_TOKEN'),
        'base_url' => env('PLIVO_BASE_URL', 'https://api.plivo.com/v1'),
        'webhook_token' => env('PLIVO_WEBHOOK_TOKEN'), // shared secret for /webhooks/sms-inbound/plivo
    ],
    // Prompt 12 §2 — Vonage (Numbers + SMS API, legacy Nexmo REST). Nigeria
    // voice restrictions/features page confirmed to exist with real
    // operational content (Caller ID guidelines, international reach) — see
    // PROGRESS.md for the verification trail.
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'base_url' => env('VONAGE_BASE_URL', 'https://rest.nexmo.com'),
        'webhook_token' => env('VONAGE_WEBHOOK_TOKEN'), // shared secret for /webhooks/sms-inbound/vonage
    ],
    // Prompt 12 §3 — Sinch (Numbers API v1 + SMS/XMS API — two separate
    // sub-products, each with its own credential type; project_id/
    // service_plan_id come from the Sinch dashboard, not the client key pair).
    'sinch' => [
        'client_id' => env('SINCH_CLIENT_ID'),
        'client_secret' => env('SINCH_CLIENT_SECRET'),
        'project_id' => env('SINCH_PROJECT_ID'),
        'api_token' => env('SINCH_API_TOKEN'),
        'service_plan_id' => env('SINCH_SERVICE_PLAN_ID'),
        'numbers_base_url' => env('SINCH_NUMBERS_BASE_URL', 'https://numbers.api.sinch.com/v1'),
        'sms_base_url' => env('SINCH_SMS_BASE_URL', 'https://us.sms.api.sinch.com/xms/v1'),
        'webhook_token' => env('SINCH_WEBHOOK_TOKEN'), // shared secret for /webhooks/sms-inbound/sinch
    ],
    'bitrefill' => [
        'api_id' => env('BITREFILL_API_ID'),
        'api_secret' => env('BITREFILL_API_SECRET'),
        'base_url' => env('BITREFILL_BASE_URL', 'https://api.bitrefill.com/v2'),
        'webhook_secret' => env('BITREFILL_WEBHOOK_SECRET'),
    ],
    'esimaccess' => [
        'api_key' => env('ESIMACCESS_API_KEY'),
        'base_url' => env('ESIMACCESS_BASE_URL', 'https://api.esimaccess.com/api/v1'),
    ],
    // Placeholder tier — adapters built, enabled later once an account exists.
    'tillo' => [
        'api_key' => env('TILLO_API_KEY'),
        'secret' => env('TILLO_SECRET'),
        'base_url' => env('TILLO_BASE_URL', 'https://sandbox.tillo.dev/api/v2'),
        'webhook_secret' => env('TILLO_WEBHOOK_SECRET'),
    ],
    'ubigi' => [
        'api_key' => env('UBIGI_API_KEY'),
        'base_url' => env('UBIGI_BASE_URL', 'https://api.ubigi.me/v1'),
    ],
    // Owner audit (2026-09-15): Sonetel is OAuth2 password-grant, not a static
    // API key — verified against Sonetel's own public api-docs repo. username/
    // password are the account login; account_id scopes the numbers endpoints.
    'sonetel' => [
        'username' => env('SONETEL_USERNAME'),
        'password' => env('SONETEL_PASSWORD'),
        'account_id' => env('SONETEL_ACCOUNT_ID'),
        'base_url' => env('SONETEL_BASE_URL', 'https://public-api.sonetel.com'),
        'auth_url' => env('SONETEL_AUTH_URL', 'https://api.sonetel.com/SonetelAuth/oauth/token'),
    ],

];
