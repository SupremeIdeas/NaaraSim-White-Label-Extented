<?php

namespace App\Support;

/**
 * Admin API-guide tooltip content (blueprint Section 15.3–15.9). Explains each
 * provider key: what it is, where to get it, the expected format, and whether
 * it's required. Rendered in the one ApiGuideModal via <x-admin-help-icon>.
 */
class ApiGuide
{
    /**
     * @return array<string, array<string, array{label: string, config_key: string, where: string, format: string, required: bool}>>
     */
    public static function all(): array
    {
        return [
            'esimgo' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'ESIMGO_API_KEY', 'where' => 'portal.esim-go.com → Account → API Keys → Generate (shown once).', 'format' => '32+ char alphanumeric', 'required' => true],
                'webhook_secret' => ['label' => 'Webhook Secret', 'config_key' => 'ESIMGO_WEBHOOK_SECRET', 'where' => 'Account → Webhooks → Create → copy secret (verifies HMAC).', 'format' => '64 char hex', 'required' => false],
                'sandbox' => ['label' => 'Sandbox', 'config_key' => 'ESIMGO_SANDBOX', 'where' => 'Admin toggle — adds the x-sandbox:on header.', 'format' => 'true / false', 'required' => false],
            ],
            'airalo' => [
                'client_id' => ['label' => 'Client ID', 'config_key' => 'AIRALO_CLIENT_ID', 'where' => 'app.partners.airalo.com → Developer → API Credentials.', 'format' => 'alphanumeric', 'required' => true],
                'client_secret' => ['label' => 'Client Secret', 'config_key' => 'AIRALO_CLIENT_SECRET', 'where' => 'Same page — shown once, store in .env.', 'format' => 'alphanumeric', 'required' => true],
                'sandbox' => ['label' => 'Sandbox', 'config_key' => 'AIRALO_SANDBOX', 'where' => 'Portal → Settings → Environment → Sandbox ON.', 'format' => 'true / false', 'required' => false],
            ],
            'getatext' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'GETATEXT_API_KEY', 'where' => 'getatext.com → Profile → API key. Sent as the Auth header. It is a WALLET key — guard it.', 'format' => 'sk_… string', 'required' => true],
                'webhook_url' => ['label' => 'Webhook URL', 'config_key' => 'GETATEXT_WEBHOOK_URL', 'where' => 'Set in the Getatext profile to your HTTPS /webhooks/getatext. Preferred over polling.', 'format' => 'HTTPS URL', 'required' => false],
            ],
            'fivesim' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'FIVESIM_API_KEY', 'where' => '5sim.net → Profile → Settings → API key. A long JWT sent as Authorization: Bearer. WALLET key — guard it.', 'format' => 'JWT (eyJ…)', 'required' => true],
            ],
            'herosms' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'HEROSMS_API_KEY', 'where' => 'hero-sms.com → API (SMS-Activate successor). Passed as a query param. Powers "Any Service" rentals. Crypto-only funding. Leave blank to keep off.', 'format' => 'alphanumeric', 'required' => false],
            ],
            'virtsms' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'VIRTSMS_API_KEY', 'where' => 'virtsms.io → API. Passed as a query param. Fallback behind HeroSMS. Leave blank to keep off.', 'format' => 'alphanumeric', 'required' => false],
            ],
            'telnyx' => [
                'api_key' => ['label' => 'API Key', 'config_key' => 'TELNYX_API_KEY', 'where' => 'portal.telnyx.com → API Keys → Create. Bearer auth. Leave blank to keep this backup off.', 'format' => 'KEY… string', 'required' => false],
            ],
            'twilio' => [
                'account_sid' => ['label' => 'Account SID', 'config_key' => 'TWILIO_ACCOUNT_SID', 'where' => 'twilio.com/console → Dashboard → Account SID.', 'format' => 'starts with AC', 'required' => false],
                'auth_token' => ['label' => 'Auth Token', 'config_key' => 'TWILIO_AUTH_TOKEN', 'where' => 'Same page → reveal Auth Token.', 'format' => '32 char hex', 'required' => false],
            ],
            'paystack' => [
                'secret_key' => ['label' => 'Secret Key', 'config_key' => 'PAYSTACK_SECRET_KEY', 'where' => 'dashboard.paystack.com → Settings → API Keys. Also signs webhooks (HMAC-SHA512).', 'format' => 'sk_live_… / sk_test_…', 'required' => false],
            ],
            'flutterwave' => [
                'secret_key' => ['label' => 'Secret Key', 'config_key' => 'FLUTTERWAVE_SECRET_KEY', 'where' => 'dashboard.flutterwave.com → Settings → API. Set the webhook secret hash too.', 'format' => 'FLWSECK_…', 'required' => false],
            ],
            'stripe' => [
                'secret_key' => ['label' => 'Secret Key', 'config_key' => 'STRIPE_SECRET_KEY', 'where' => 'dashboard.stripe.com → Developers → API keys. Add the webhook signing secret.', 'format' => 'sk_live_… / sk_test_…', 'required' => false],
            ],
        ];
    }

    public static function for(string $provider, string $field): ?array
    {
        return self::all()[$provider][$field] ?? null;
    }
}
