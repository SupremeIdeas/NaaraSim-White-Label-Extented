<?php

namespace App\Support;

/**
 * Prompt 10 — named mobile-money rails per (gateway, currency), verified
 * against each gateway's own developer/support docs rather than guessed from
 * the generic "mobile money" claim the wallet top-up hints used to make:
 *
 *  - Paystack's own charge API and support docs (paystack.com/docs/api/charge,
 *    support.paystack.com "Pay with Mobile Money" / "Pay with M-PESA") scope
 *    the `mobile_money` channel to Ghana (mtn/atl/vod), Kenya (mpesa), and
 *    Côte d'Ivoire (orange/wave) — NOT Nigeria or South Africa.
 *  - Flutterwave's developer docs (developer.flutterwave.com/docs/ghana,
 *    .../uganda, .../zambia-mobile-money) and help centre confirm the same
 *    shape: Ghana (MTN/Vodafone/AirtelTigo), Kenya (M-Pesa) — again, no
 *    mobile-money channel for Nigeria or South Africa.
 *
 * Scope is deliberately restricted to currencies this platform actually
 * models (CurrencyService::SUPPORTED / GatewayCurrencyMatrix's existing NG/
 * GH/KE/ZA countries) — Uganda/Rwanda/Tanzania/Zambia rails exist on both
 * gateways too, but adding them here without first adding UGX/RWF/TZS/ZMW to
 * CurrencyService would advertise a currency the platform can't yet display
 * or settle, so they're intentionally left out until that groundwork exists.
 */
class MobileMoneyRails
{
    /**
     * gateway => [currency => named rails].
     *
     * @var array<string, array<string, list<string>>>
     */
    public const RAILS = [
        'paystack' => [
            'GHS' => ['MTN Mobile Money', 'AirtelTigo Money', 'Vodafone Cash'],
            'KES' => ['M-Pesa'],
        ],
        'flutterwave' => [
            'GHS' => ['MTN Mobile Money', 'Vodafone Cash', 'AirtelTigo Money'],
            'KES' => ['M-Pesa'],
        ],
    ];

    /** @return list<string> named rails, or [] if this gateway/currency pairing has none. */
    public static function forGatewayCurrency(string $gateway, string $currency): array
    {
        return self::RAILS[$gateway][strtoupper($currency)] ?? [];
    }
}
