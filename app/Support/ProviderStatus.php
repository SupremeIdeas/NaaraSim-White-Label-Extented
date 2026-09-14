<?php

namespace App\Support;

/**
 * Active / Coming-Soon by real config (blueprint Section 17.4). A product is
 * "Coming Soon" while its provider key is empty and flips to "Active" — and
 * starts calling the real API — the moment a valid key is saved. Real logic
 * driven by config, so the operator can launch one product at a time.
 */
class ProviderStatus
{
    /** provider => config keys that must all be non-empty to be Active. */
    private const REQUIRED = [
        'esimgo' => ['services.esimgo.api_key'],
        'airalo' => ['services.airalo.client_id', 'services.airalo.client_secret'],
        'quibity' => ['services.quibity.api_key'],
        'zendit' => ['services.zendit.api_key'],
        'reloadly' => ['services.reloadly.client_id', 'services.reloadly.client_secret'],
        'oneglobal' => ['services.oneglobal.client_id', 'services.oneglobal.client_secret'],
        'montymobile' => ['services.montymobile.api_key'],
        'gigs' => ['services.gigs.api_key', 'services.gigs.project'],
        'esimaccess' => ['services.esimaccess.api_key'],
        'ubigi' => ['services.ubigi.api_key'],
        'getatext' => ['services.getatext.api_key'],
        'fivesim' => ['services.fivesim.api_key'],
        'herosms' => ['services.herosms.api_key'],
        'virtsms' => ['services.virtsms.api_key'],
        'twilio' => ['services.twilio.account_sid', 'services.twilio.auth_token'],
        'telnyx' => ['services.telnyx.api_key'],
        'smspool' => ['services.smspool.api_key'],
        'onlinesim' => ['services.onlinesim.api_key'],
        'plivo' => ['services.plivo.auth_id', 'services.plivo.auth_token'],
        'sonetel' => ['services.sonetel.api_key'],
        'vonage' => ['services.vonage.api_key', 'services.vonage.api_secret'],
        'sinch' => ['services.sinch.client_id', 'services.sinch.client_secret', 'services.sinch.project_id'],
        'bitrefill' => ['services.bitrefill.api_id', 'services.bitrefill.api_secret'],
        'tillo' => ['services.tillo.api_key', 'services.tillo.secret'],
        'whatsapp' => ['services.whatsapp.phone_number_id', 'services.whatsapp.access_token'],
        'paystack' => ['services.paystack.secret_key'],
        'flutterwave' => ['services.flutterwave.secret_key'],
        'stripe' => ['services.stripe.secret_key'],
        'paypal' => ['services.paypal.client_id', 'services.paypal.client_secret'],
        'binance' => ['services.binance.api_key', 'services.binance.api_secret'],
        'nowpayments' => ['services.nowpayments.api_key'],
        'cryptomus' => ['services.cryptomus.merchant_id', 'services.cryptomus.api_key'],
        'coinpayments' => ['services.coinpayments.public_key', 'services.coinpayments.private_key'],
        'payssion' => ['services.payssion.api_key', 'services.payssion.secret_key'],
    ];

    public static function isActive(string $provider): bool
    {
        foreach (self::REQUIRED[$provider] ?? [] as $key) {
            if (empty(config($key))) {
                return false;
            }
        }

        return isset(self::REQUIRED[$provider]);
    }

    public static function label(string $provider): string
    {
        return self::isActive($provider) ? 'Active' : 'Coming Soon';
    }

    /** @return array<string, string> provider => Active|Coming Soon */
    public static function all(): array
    {
        return collect(array_keys(self::REQUIRED))
            ->mapWithKeys(fn ($p) => [$p => self::label($p)])
            ->all();
    }
}
