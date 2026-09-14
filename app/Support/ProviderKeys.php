<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Admin-managed API credentials (blueprint Sections 15 & 17.4, money-safety
 * rule 10). Every provider/gateway/integration secret can be pasted and saved
 * from the admin panel instead of hand-editing .env — the operator has zero
 * coding knowledge and shared cPanel makes editing .env awkward.
 *
 * Storage: one encrypted Setting row (`providers.keys`) holding a
 * config-path => value map. At boot, applyToConfig() overlays any saved value
 * on top of config() so EVERY service keeps reading config('services.*') with
 * no code change, and ProviderStatus flips Active the moment a key is saved.
 *
 * Precedence: an admin-saved value WINS over the .env value (an operator who
 * pastes a key in the panel expects it to take effect). A blank field is
 * treated as "not set" and falls through to whatever .env provides, so the
 * two mechanisms coexist. Values are encrypted at rest by the Setting cast and
 * NEVER exposed — the admin UI shows a masked preview, not the raw secret.
 */
class ProviderKeys
{
    /** The single Setting row that holds the config-path => value map. */
    public const SETTING_KEY = 'providers.keys';

    private const CACHE_KEY = 'providers.keys.resolved';

    /**
     * The full credential schema, grouped for the admin UI. Each field maps a
     * human label to the exact config path it overrides and the .env variable
     * it mirrors. `secret` fields are masked in the UI and never echoed back.
     *
     * @return array<string, array{label: string, fields: array<string, array{label: string, config: string, env: string, secret: bool, hint: string}>}>
     */
    public static function schema(): array
    {
        return [
            'esim' => [
                'label' => 'eSIM data providers',
                'fields' => [
                    'esimgo_api_key' => ['label' => 'eSIM Go — API Key', 'config' => 'services.esimgo.api_key', 'env' => 'ESIMGO_API_KEY', 'secret' => true, 'hint' => 'portal.esim-go.com → Account → API Keys (PRIMARY).'],
                    'esimgo_webhook_secret' => ['label' => 'eSIM Go — Webhook Secret', 'config' => 'services.esimgo.webhook_secret', 'env' => 'ESIMGO_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Account → Webhooks — verifies the HMAC signature.'],
                    'airalo_client_id' => ['label' => 'Airalo — Client ID', 'config' => 'services.airalo.client_id', 'env' => 'AIRALO_CLIENT_ID', 'secret' => false, 'hint' => 'app.partners.airalo.com → Developer (SECONDARY).'],
                    'airalo_client_secret' => ['label' => 'Airalo — Client Secret', 'config' => 'services.airalo.client_secret', 'env' => 'AIRALO_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same page — shown once.'],
                    'quibity_api_key' => ['label' => 'Quibity / eSIM.sm — API Key', 'config' => 'services.quibity.api_key', 'env' => 'QUIBITY_API_KEY', 'secret' => true, 'hint' => 'esim.sm reseller dashboard (TERTIARY).'],
                    'zendit_api_key' => ['label' => 'Zendit — API Key (Naara Connect + data)', 'config' => 'services.zendit.api_key', 'env' => 'ZENDIT_API_KEY', 'secret' => true, 'hint' => 'developers.zendit.io → API keys. Full eSIMs (calls + data) AND plain data eSIMs. Use a sand_… test key with ZENDIT_SANDBOX=true first.'],
                    'zendit_webhook_secret' => ['label' => 'Zendit — Webhook Secret', 'config' => 'services.zendit.webhook_secret', 'env' => 'ZENDIT_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Verifies the Naara Gift async voucher-delivery webhook (/webhooks/giftcards/zendit).'],
                    'oneglobal_client_id' => ['label' => '1GLOBAL — Client ID (Naara Connect)', 'config' => 'services.oneglobal.client_id', 'env' => 'ONEGLOBAL_CLIENT_ID', 'secret' => false, 'hint' => 'docs.connect.1global.com — partner access. Full eSIM (voice + data).'],
                    'oneglobal_client_secret' => ['label' => '1GLOBAL — Client Secret', 'config' => 'services.oneglobal.client_secret', 'env' => 'ONEGLOBAL_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same Connect app — OAuth2 client-credentials secret.'],
                    'montymobile_api_key' => ['label' => 'Monty Mobile — RSP API Key (Naara Connect)', 'config' => 'services.montymobile.api_key', 'env' => 'MONTYMOBILE_API_KEY', 'secret' => true, 'hint' => 'montymobile.com → partner/sales for RSP API access. Full eSIM (voice + data).'],
                    'gigs_api_key' => ['label' => 'Gigs — API Key (Naara Connect)', 'config' => 'services.gigs.api_key', 'env' => 'GIGS_API_KEY', 'secret' => true, 'hint' => 'developers.gigs.com → API keys. Full MVNO eSIM (voice + SMS + data + number).'],
                    'gigs_project' => ['label' => 'Gigs — Project ID', 'config' => 'services.gigs.project', 'env' => 'GIGS_PROJECT', 'secret' => false, 'hint' => 'Gigs dashboard → the project every resource is scoped to.'],
                    'esimaccess_api_key' => ['label' => 'eSIMAccess — API Key (data eSIM)', 'config' => 'services.esimaccess.api_key', 'env' => 'ESIMACCESS_API_KEY', 'secret' => true, 'hint' => 'esimaccess.com reseller dashboard → API. Data eSIM, same lane as eSIM Go / Airalo.'],
                    'ubigi_api_key' => ['label' => 'Ubigi — API Key (data eSIM)', 'config' => 'services.ubigi.api_key', 'env' => 'UBIGI_API_KEY', 'secret' => true, 'hint' => 'ubigi.me partner portal → API. Data eSIM lane.'],
                ],
            ],
            'numbers' => [
                'label' => 'Number & SMS providers',
                'fields' => [
                    'getatext_api_key' => ['label' => 'Getatext — API Key', 'config' => 'services.getatext.api_key', 'env' => 'GETATEXT_API_KEY', 'secret' => true, 'hint' => 'getatext.com → Profile → API key. WALLET key — guard it.'],
                    'fivesim_api_key' => ['label' => '5sim — API Key', 'config' => 'services.fivesim.api_key', 'env' => 'FIVESIM_API_KEY', 'secret' => true, 'hint' => '5sim.net → Profile → Settings. JWT. WALLET key.'],
                    'herosms_api_key' => ['label' => 'HeroSMS — API Key', 'config' => 'services.herosms.api_key', 'env' => 'HEROSMS_API_KEY', 'secret' => true, 'hint' => 'hero-sms.com → API (SMS-Activate successor). Powers "Any Service" rentals. WALLET key — crypto-only funding.'],
                    'virtsms_api_key' => ['label' => 'VirtSMS — API Key', 'config' => 'services.virtsms.api_key', 'env' => 'VIRTSMS_API_KEY', 'secret' => true, 'hint' => 'virtsms.io → API. Fallback behind HeroSMS in the same lane.'],
                    'twilio_account_sid' => ['label' => 'Twilio — Account SID', 'config' => 'services.twilio.account_sid', 'env' => 'TWILIO_ACCOUNT_SID', 'secret' => false, 'hint' => 'twilio.com/console (permanent numbers + voice).'],
                    'twilio_auth_token' => ['label' => 'Twilio — Auth Token', 'config' => 'services.twilio.auth_token', 'env' => 'TWILIO_AUTH_TOKEN', 'secret' => true, 'hint' => 'Same page — reveal Auth Token.'],
                    'telnyx_api_key' => ['label' => 'Telnyx — API Key', 'config' => 'services.telnyx.api_key', 'env' => 'TELNYX_API_KEY', 'secret' => true, 'hint' => 'portal.telnyx.com → API Keys (permanent/voice backup).'],
                    'smspool_api_key' => ['label' => 'SMSPool — API Key', 'config' => 'services.smspool.api_key', 'env' => 'SMSPOOL_API_KEY', 'secret' => true, 'hint' => 'smspool.net → Profile → API. OTP / verification lane. WALLET key.'],
                    'onlinesim_api_key' => ['label' => 'OnlineSIM — API Key', 'config' => 'services.onlinesim.api_key', 'env' => 'ONLINESIM_API_KEY', 'secret' => true, 'hint' => 'onlinesim.io → Profile → API. OTP / verification lane. WALLET key.'],
                    'plivo_auth_id' => ['label' => 'Plivo — Auth ID', 'config' => 'services.plivo.auth_id', 'env' => 'PLIVO_AUTH_ID', 'secret' => false, 'hint' => 'console.plivo.com → Account. Permanent numbers + SMS (no African voice).'],
                    'plivo_auth_token' => ['label' => 'Plivo — Auth Token', 'config' => 'services.plivo.auth_token', 'env' => 'PLIVO_AUTH_TOKEN', 'secret' => true, 'hint' => 'Same page — the Auth Token.'],
                    'plivo_webhook_token' => ['label' => 'Plivo — Inbound Webhook Token', 'config' => 'services.plivo.webhook_token', 'env' => 'PLIVO_WEBHOOK_TOKEN', 'secret' => true, 'hint' => 'Any random string you also set as ?token= on the Plivo inbound-SMS webhook URL.'],
                    'sonetel_api_key' => ['label' => 'Sonetel — API Key', 'config' => 'services.sonetel.api_key', 'env' => 'SONETEL_API_KEY', 'secret' => true, 'hint' => 'sonetel.com → Settings → API. Permanent numbers + voice.'],
                    'vonage_api_key' => ['label' => 'Vonage — API Key', 'config' => 'services.vonage.api_key', 'env' => 'VONAGE_API_KEY', 'secret' => false, 'hint' => 'dashboard.nexmo.com → API keys. Permanent numbers + voice/SMS.'],
                    'vonage_api_secret' => ['label' => 'Vonage — API Secret', 'config' => 'services.vonage.api_secret', 'env' => 'VONAGE_API_SECRET', 'secret' => true, 'hint' => 'Same page — the API Secret.'],
                    'vonage_webhook_token' => ['label' => 'Vonage — Inbound Webhook Token', 'config' => 'services.vonage.webhook_token', 'env' => 'VONAGE_WEBHOOK_TOKEN', 'secret' => true, 'hint' => 'Any random string you also set as ?token= on the Vonage inbound-SMS webhook URL.'],
                    'sinch_client_id' => ['label' => 'Sinch — Client ID (Numbers API)', 'config' => 'services.sinch.client_id', 'env' => 'SINCH_CLIENT_ID', 'secret' => false, 'hint' => 'dashboard.sinch.com → Access Keys. Permanent numbers (SMS confirmed; voice unverified in Naara\'s markets).'],
                    'sinch_client_secret' => ['label' => 'Sinch — Client Secret (Numbers API)', 'config' => 'services.sinch.client_secret', 'env' => 'SINCH_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same page — the Access Key Secret.'],
                    'sinch_project_id' => ['label' => 'Sinch — Project ID', 'config' => 'services.sinch.project_id', 'env' => 'SINCH_PROJECT_ID', 'secret' => false, 'hint' => 'dashboard.sinch.com → the project every Numbers API call is scoped to.'],
                    'sinch_api_token' => ['label' => 'Sinch — SMS API Token', 'config' => 'services.sinch.api_token', 'env' => 'SINCH_API_TOKEN', 'secret' => true, 'hint' => 'dashboard.sinch.com → APIs → SMS. A separate Bearer token from the Numbers API keys above.'],
                    'sinch_service_plan_id' => ['label' => 'Sinch — SMS Service Plan ID', 'config' => 'services.sinch.service_plan_id', 'env' => 'SINCH_SERVICE_PLAN_ID', 'secret' => false, 'hint' => 'Same SMS API page — the Service Plan ID.'],
                    'sinch_webhook_token' => ['label' => 'Sinch — Inbound Webhook Token', 'config' => 'services.sinch.webhook_token', 'env' => 'SINCH_WEBHOOK_TOKEN', 'secret' => true, 'hint' => 'Any random string you also set as ?token= on the Sinch inbound-SMS webhook URL.'],
                ],
            ],
            'payments' => [
                'label' => 'Payment gateways',
                'fields' => [
                    'paystack_secret_key' => ['label' => 'Paystack — Secret Key', 'config' => 'services.paystack.secret_key', 'env' => 'PAYSTACK_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.paystack.com → Settings → API Keys. Also signs webhooks.'],
                    'flutterwave_secret_key' => ['label' => 'Flutterwave — Secret Key', 'config' => 'services.flutterwave.secret_key', 'env' => 'FLUTTERWAVE_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.flutterwave.com → Settings → API.'],
                    'flutterwave_secret_hash' => ['label' => 'Flutterwave — Secret Hash', 'config' => 'services.flutterwave.secret_hash', 'env' => 'FLUTTERWAVE_SECRET_HASH', 'secret' => true, 'hint' => 'The verif-hash header used to verify webhooks.'],
                    'stripe_secret_key' => ['label' => 'Stripe — Secret Key', 'config' => 'services.stripe.secret_key', 'env' => 'STRIPE_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.stripe.com → Developers → API keys.'],
                    'stripe_webhook_secret' => ['label' => 'Stripe — Webhook Secret', 'config' => 'services.stripe.webhook_secret', 'env' => 'STRIPE_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Developers → Webhooks → signing secret (whsec_…).'],
                    'stripe_connect_webhook_secret' => ['label' => 'Stripe — Connect Webhook Secret', 'config' => 'services.stripe.connect_webhook_secret', 'env' => 'STRIPE_CONNECT_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'A SEPARATE webhook endpoint in Developers → Webhooks for Connect account.updated events — its own signing secret, not the one above.'],
                ],
            ],
            'identity' => [
                'label' => 'Identity / KYC',
                'fields' => [
                    'smileid_partner_id' => ['label' => 'Smile ID — Partner ID', 'config' => 'services.smileid.partner_id', 'env' => 'SMILEID_PARTNER_ID', 'secret' => false, 'hint' => 'portal.smileidentity.com → Settings. Pan-African BVN/NIN + liveness (good default). Then set the active provider on Admin → Payouts.'],
                    'smileid_api_key' => ['label' => 'Smile ID — API Key', 'config' => 'services.smileid.api_key', 'env' => 'SMILEID_API_KEY', 'secret' => true, 'hint' => 'Same page — API key. Also verifies the result callback.'],
                    'dojah_app_id' => ['label' => 'Dojah — App ID', 'config' => 'services.dojah.app_id', 'env' => 'DOJAH_APP_ID', 'secret' => false, 'hint' => 'app.dojah.io → your app. Fast BVN/NIN/document checks.'],
                    'dojah_api_key' => ['label' => 'Dojah — API Key', 'config' => 'services.dojah.api_key', 'env' => 'DOJAH_API_KEY', 'secret' => true, 'hint' => 'Same app — the private/secret key.'],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'fields' => [
                    'anthropic_api_key' => ['label' => 'Anthropic — API Key', 'config' => 'services.anthropic.api_key', 'env' => 'ANTHROPIC_API_KEY', 'secret' => true, 'hint' => 'console.anthropic.com → API Keys (maintenance loop, Section 29).'],
                    'github_maintenance_token' => ['label' => 'GitHub — Maintenance Token', 'config' => 'services.github_maintenance.token', 'env' => 'GITHUB_MAINTENANCE_TOKEN', 'secret' => true, 'hint' => 'Fine-grained PAT scoped to THIS repo only (opens CI-gated PRs).'],
                ],
            ],
            'social' => [
                'label' => 'Social login',
                'fields' => [
                    'google_client_id' => ['label' => 'Google — Client ID', 'config' => 'services.google.client_id', 'env' => 'GOOGLE_CLIENT_ID', 'secret' => false, 'hint' => 'console.cloud.google.com → APIs & Services → Credentials → Create OAuth client ID (Web).'],
                    'google_client_secret' => ['label' => 'Google — Client Secret', 'config' => 'services.google.client_secret', 'env' => 'GOOGLE_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same OAuth client — copy the secret. Redirect URI: <your-site>/auth/google/callback.'],
                    'facebook_client_id' => ['label' => 'Facebook — App ID', 'config' => 'services.facebook.client_id', 'env' => 'FACEBOOK_CLIENT_ID', 'secret' => false, 'hint' => 'developers.facebook.com/apps → add Facebook Login. Redirect: <your-site>/auth/facebook/callback.'],
                    'facebook_client_secret' => ['label' => 'Facebook — App Secret', 'config' => 'services.facebook.client_secret', 'env' => 'FACEBOOK_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same app → Settings → Basic → App Secret.'],
                    'twitter_client_id' => ['label' => 'X (Twitter) — Client ID', 'config' => 'services.twitter.client_id', 'env' => 'TWITTER_CLIENT_ID', 'secret' => false, 'hint' => 'developer.x.com → OAuth 2.0 app. Redirect: <your-site>/auth/twitter/callback.'],
                    'twitter_client_secret' => ['label' => 'X (Twitter) — Client Secret', 'config' => 'services.twitter.client_secret', 'env' => 'TWITTER_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same OAuth 2.0 app — Client Secret.'],
                    'apple_client_id' => ['label' => 'Apple — Services ID', 'config' => 'services.apple.client_id', 'env' => 'APPLE_CLIENT_ID', 'secret' => false, 'hint' => 'developer.apple.com → Identifiers → Services ID. Return URL: <your-site>/auth/apple/callback.'],
                    'apple_client_secret' => ['label' => 'Apple — Client Secret (JWT)', 'config' => 'services.apple.client_secret', 'env' => 'APPLE_CLIENT_SECRET', 'secret' => true, 'hint' => 'Generate the client-secret JWT from your Sign-in-with-Apple key.'],
                    'microsoft_client_id' => ['label' => 'Microsoft — Client ID', 'config' => 'services.microsoft.client_id', 'env' => 'MICROSOFT_CLIENT_ID', 'secret' => false, 'hint' => 'portal.azure.com → Entra ID → App registrations. Redirect: <your-site>/auth/microsoft/callback.'],
                    'microsoft_client_secret' => ['label' => 'Microsoft — Client Secret', 'config' => 'services.microsoft.client_secret', 'env' => 'MICROSOFT_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same app → Certificates & secrets → New client secret.'],
                    'discord_client_id' => ['label' => 'Discord — Client ID', 'config' => 'services.discord.client_id', 'env' => 'DISCORD_CLIENT_ID', 'secret' => false, 'hint' => 'discord.com/developers/applications → OAuth2. Redirect: <your-site>/auth/discord/callback.'],
                    'discord_client_secret' => ['label' => 'Discord — Client Secret', 'config' => 'services.discord.client_secret', 'env' => 'DISCORD_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same app → OAuth2 → Client Secret.'],
                ],
            ],
            'crypto_payments' => [
                'label' => 'Payment gateways — PayPal & crypto',
                'fields' => [
                    'paypal_client_id' => ['label' => 'PayPal — Client ID', 'config' => 'services.paypal.client_id', 'env' => 'PAYPAL_CLIENT_ID', 'secret' => false, 'hint' => 'developer.paypal.com → Apps & Credentials (Live).'],
                    'paypal_client_secret' => ['label' => 'PayPal — Client Secret', 'config' => 'services.paypal.client_secret', 'env' => 'PAYPAL_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same app. Set the webhook to <your-site>/webhooks/payments/paypal and paste its ID below.'],
                    'paypal_webhook_id' => ['label' => 'PayPal — Webhook ID', 'config' => 'services.paypal.webhook_id', 'env' => 'PAYPAL_WEBHOOK_ID', 'secret' => false, 'hint' => 'Developer dashboard → Webhooks → the webhook ID (verifies signatures).'],
                    'binance_api_key' => ['label' => 'Binance Pay — API Key', 'config' => 'services.binance.api_key', 'env' => 'BINANCE_PAY_API_KEY', 'secret' => false, 'hint' => 'merchant.binance.com → Pay → API. Webhook: <your-site>/webhooks/payments/binance.'],
                    'binance_api_secret' => ['label' => 'Binance Pay — API Secret', 'config' => 'services.binance.api_secret', 'env' => 'BINANCE_PAY_API_SECRET', 'secret' => true, 'hint' => 'Same page — signs requests + webhooks (HMAC-SHA512).'],
                    'nowpayments_api_key' => ['label' => 'NOWPayments — API Key', 'config' => 'services.nowpayments.api_key', 'env' => 'NOWPAYMENTS_API_KEY', 'secret' => true, 'hint' => 'account.nowpayments.io → Settings → API keys.'],
                    'nowpayments_ipn_secret' => ['label' => 'NOWPayments — IPN Secret', 'config' => 'services.nowpayments.ipn_secret', 'env' => 'NOWPAYMENTS_IPN_SECRET', 'secret' => true, 'hint' => 'Settings → IPN. Callback: <your-site>/webhooks/payments/nowpayments.'],
                    'cryptomus_merchant_id' => ['label' => 'Cryptomus — Merchant UUID', 'config' => 'services.cryptomus.merchant_id', 'env' => 'CRYPTOMUS_MERCHANT_ID', 'secret' => false, 'hint' => 'app.cryptomus.com → Merchant settings.'],
                    'cryptomus_api_key' => ['label' => 'Cryptomus — Payment API Key', 'config' => 'services.cryptomus.api_key', 'env' => 'CRYPTOMUS_API_KEY', 'secret' => true, 'hint' => 'Same page. Callback: <your-site>/webhooks/payments/cryptomus.'],
                    'coinpayments_public_key' => ['label' => 'CoinPayments — Public Key', 'config' => 'services.coinpayments.public_key', 'env' => 'COINPAYMENTS_PUBLIC_KEY', 'secret' => false, 'hint' => 'coinpayments.net → Account → API Keys.'],
                    'coinpayments_private_key' => ['label' => 'CoinPayments — Private Key', 'config' => 'services.coinpayments.private_key', 'env' => 'COINPAYMENTS_PRIVATE_KEY', 'secret' => true, 'hint' => 'Same page — signs create-transaction.'],
                    'coinpayments_ipn_secret' => ['label' => 'CoinPayments — IPN Secret', 'config' => 'services.coinpayments.ipn_secret', 'env' => 'COINPAYMENTS_IPN_SECRET', 'secret' => true, 'hint' => 'Account → Merchant Settings. IPN: <your-site>/webhooks/payments/coinpayments.'],
                    'coinpayments_merchant_id' => ['label' => 'CoinPayments — Merchant ID', 'config' => 'services.coinpayments.merchant_id', 'env' => 'COINPAYMENTS_MERCHANT_ID', 'secret' => false, 'hint' => 'Account → Merchant Settings — checked on each IPN.'],
                    'payssion_api_key' => ['label' => 'Payssion — API Key', 'config' => 'services.payssion.api_key', 'env' => 'PAYSSION_API_KEY', 'secret' => false, 'hint' => 'payssion.com → Merchant → API.'],
                    'payssion_secret_key' => ['label' => 'Payssion — Secret Key', 'config' => 'services.payssion.secret_key', 'env' => 'PAYSSION_SECRET_KEY', 'secret' => true, 'hint' => 'Same page. Notify URL: <your-site>/webhooks/payments/payssion.'],
                ],
            ],
            'giftcards' => [
                'label' => 'Gift cards & airtime',
                'fields' => [
                    'reloadly_client_id' => ['label' => 'Reloadly — Client ID', 'config' => 'services.reloadly.client_id', 'env' => 'RELOADLY_CLIENT_ID', 'secret' => false, 'hint' => 'reloadly.com → Developers → API settings. Airtime + gift cards. Use RELOADLY_SANDBOX=true first.'],
                    'reloadly_client_secret' => ['label' => 'Reloadly — Client Secret', 'config' => 'services.reloadly.client_secret', 'env' => 'RELOADLY_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same page — the OAuth client-credentials secret.'],
                    'reloadly_webhook_secret' => ['label' => 'Reloadly — Webhook Secret', 'config' => 'services.reloadly.webhook_secret', 'env' => 'RELOADLY_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Verifies the Naara Gift async order-delivery webhook (/webhooks/giftcards/reloadly).'],
                    'bitrefill_api_id' => ['label' => 'Bitrefill — API ID', 'config' => 'services.bitrefill.api_id', 'env' => 'BITREFILL_API_ID', 'secret' => false, 'hint' => 'bitrefill.com → Account → API. Gift cards + airtime (crypto-funded).'],
                    'bitrefill_api_secret' => ['label' => 'Bitrefill — API Secret', 'config' => 'services.bitrefill.api_secret', 'env' => 'BITREFILL_API_SECRET', 'secret' => true, 'hint' => 'Same page — signs requests.'],
                    'bitrefill_webhook_secret' => ['label' => 'Bitrefill — Webhook Secret', 'config' => 'services.bitrefill.webhook_secret', 'env' => 'BITREFILL_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Verifies Bitrefill\'s invoice-completed webhook (/webhooks/giftcards/bitrefill).'],
                    'tillo_api_key' => ['label' => 'Tillo — API Key', 'config' => 'services.tillo.api_key', 'env' => 'TILLO_API_KEY', 'secret' => false, 'hint' => 'tillo.io merchant portal → API. Gift cards. Sandbox host until live access.'],
                    'tillo_secret' => ['label' => 'Tillo — Secret', 'config' => 'services.tillo.secret', 'env' => 'TILLO_SECRET', 'secret' => true, 'hint' => 'Same portal — the HMAC signing secret.'],
                    'tillo_webhook_secret' => ['label' => 'Tillo — Webhook Secret', 'config' => 'services.tillo.webhook_secret', 'env' => 'TILLO_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Verifies Tillo\'s order webhook (/webhooks/giftcards/tillo).'],
                ],
            ],
            'voice' => [
                'label' => 'Voice (ElevenLabs)',
                'fields' => [
                    'elevenlabs_api_key' => ['label' => 'ElevenLabs — API Key', 'config' => 'services.elevenlabs.api_key', 'env' => 'ELEVENLABS_API_KEY', 'secret' => true, 'hint' => 'elevenlabs.io → Profile icon → API Keys → Create. Powers spoken support replies for paying customers.'],
                    'elevenlabs_voice_id' => ['label' => 'ElevenLabs — Voice ID', 'config' => 'services.elevenlabs.voice_id', 'env' => 'ELEVENLABS_VOICE_ID', 'secret' => false, 'hint' => 'elevenlabs.io → Voices → pick/clone a voice → copy its Voice ID.'],
                    'elevenlabs_model' => ['label' => 'ElevenLabs — Model', 'config' => 'services.elevenlabs.model', 'env' => 'ELEVENLABS_MODEL', 'secret' => false, 'hint' => 'Use eleven_v3 for the most expressive, human delivery (supports [laughs], [exhales] tags). Leave as eleven_v3 if unsure.'],
                ],
            ],
            'storage' => [
                'label' => 'Media storage — Cloudflare R2 (optional)',
                'fields' => [
                    'r2_access_key_id' => ['label' => 'R2 — Access Key ID', 'config' => 'filesystems.disks.r2.key', 'env' => 'R2_ACCESS_KEY_ID', 'secret' => false, 'hint' => 'dash.cloudflare.com → R2 → Manage R2 API Tokens → Create. Leave every R2 field blank to keep using Wasabi / the server disk.'],
                    'r2_secret_access_key' => ['label' => 'R2 — Secret Access Key', 'config' => 'filesystems.disks.r2.secret', 'env' => 'R2_SECRET_ACCESS_KEY', 'secret' => true, 'hint' => 'Shown once when the R2 API token is created.'],
                    'r2_bucket' => ['label' => 'R2 — Bucket name', 'config' => 'filesystems.disks.r2.bucket', 'env' => 'R2_BUCKET', 'secret' => false, 'hint' => 'R2 → the bucket that will hold uploads.'],
                    'r2_endpoint' => ['label' => 'R2 — S3 API endpoint', 'config' => 'filesystems.disks.r2.endpoint', 'env' => 'R2_ENDPOINT', 'secret' => false, 'hint' => 'https://<ACCOUNT_ID>.r2.cloudflarestorage.com — the account ID is on the R2 overview page.'],
                    'r2_public_url' => ['label' => 'R2 — Public URL', 'config' => 'filesystems.disks.r2.url', 'env' => 'R2_PUBLIC_URL', 'secret' => false, 'hint' => 'The bucket public r2.dev URL or your custom domain used to SERVE files. Required before R2 can hold PUBLIC media (the S3 endpoint above needs auth).'],
                ],
            ],
            'turnstile' => [
                'label' => 'Bot protection (Cloudflare Turnstile)',
                'fields' => [
                    'turnstile_site_key' => ['label' => 'Turnstile — Site Key', 'config' => 'services.turnstile.site_key', 'env' => 'TURNSTILE_SITE_KEY', 'secret' => false, 'hint' => 'dash.cloudflare.com → Turnstile → Add site. The PUBLIC site key (shown in the widget). Then flip the toggle on the Security page.'],
                    'turnstile_secret_key' => ['label' => 'Turnstile — Secret Key', 'config' => 'services.turnstile.secret_key', 'env' => 'TURNSTILE_SECRET_KEY', 'secret' => true, 'hint' => 'Same Turnstile site — the SECRET key used to verify the challenge server-side.'],
                ],
            ],
        ];
    }

    /** Flat field-name => config-path map, for validation and lookups. */
    public static function fieldMap(): array
    {
        $map = [];
        foreach (self::schema() as $group) {
            foreach ($group['fields'] as $name => $meta) {
                $map[$name] = $meta['config'];
            }
        }

        return $map;
    }

    /**
     * The saved config-path => value map (only non-empty values are kept).
     * Cached forever; busted on save via flush().
     *
     * @return array<string, string>
     */
    public static function saved(): array
    {
        // Wrap the whole cache call, not just the DB read: this runs at boot on
        // every request, so a broken/unreachable cache or DB backend (e.g.
        // pre-install, or Redis momentarily down) must degrade to "nothing to
        // overlay" instead of taking the whole app down. .env still fills keys.
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $stored = Setting::getValue(self::SETTING_KEY, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Overlay every admin-saved credential on top of config() so all services
     * keep reading config('services.*') unchanged. Called once per request/job
     * from AppServiceProvider::boot(). Blank values are skipped so .env still
     * fills any field the admin left empty.
     */
    public static function applyToConfig(): void
    {
        foreach (self::saved() as $configPath => $value) {
            if ($value !== null && $value !== '') {
                config([$configPath => $value]);
            }
        }
    }

    /**
     * Persist a field-name => value map from the admin form. Blank fields are
     * removed (fall back to .env); non-blank fields override .env. Stored as a
     * config-path => value map so applyToConfig() can overlay it directly.
     *
     * @param  array<string, string|null>  $values  keyed by schema field name
     */
    public static function save(array $values): void
    {
        $fieldMap = self::fieldMap();
        $map = self::saved(); // start from what's already stored (config-path keyed)

        foreach ($values as $field => $value) {
            if (! isset($fieldMap[$field])) {
                continue; // ignore anything not in the schema
            }
            $configPath = $fieldMap[$field];
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                unset($map[$configPath]);
            } else {
                $map[$configPath] = $value;
            }
        }

        Setting::setValue(self::SETTING_KEY, $map, 'providers', 'Admin-managed API credentials (encrypted).');
        self::flush();
        self::applyToConfig(); // take effect within this same request

        // CRITICAL (esim_upgrade Part 1): a web request re-boots the app per
        // request and picks up new keys immediately — but a long-running queue
        // worker (Horizon) booted ONCE keeps using the OLD config for hours,
        // so every queued catalogue sync silently keeps failing on stale keys.
        // Signal all workers to gracefully restart so they re-boot with the new
        // credentials. Applied centrally here, once, for EVERY credential group.
        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $e) {
            Log::warning('[providers] queue:restart after key save failed: '.$e->getMessage());
        }
    }

    /** True if this field currently has an admin-saved value (any source). */
    public static function hasValue(string $field): bool
    {
        $config = self::fieldMap()[$field] ?? null;

        return $config !== null && ! empty(config($config));
    }

    /** A masked preview of a saved value for display (never the raw secret). */
    public static function preview(string $field): ?string
    {
        $config = self::fieldMap()[$field] ?? null;
        $value = $config ? (string) config($config) : '';

        if ($value === '') {
            return null;
        }

        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', min($len - 4, 12)).substr($value, -4);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isProviderKey(string $key): bool
    {
        return $key === self::SETTING_KEY;
    }
}
