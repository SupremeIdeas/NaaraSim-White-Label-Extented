<?php

/*
 * Global Payout Layer settings that are code-level constants rather than admin
 * toggles (the admin-editable ones live in App\Support\PayoutSettings).
 */
return [
    // Providers that make up the "global rail" (Addendum A). Rails that are not built yet
    // simply have no accounts/enrollments; listing them here costs nothing.
    'global_rail_providers' => ['payoneer', 'grey', 'stripe_global', 'manual_external'],

    // Providers that debit float at SUBMIT time (so in-flight is already out of float and is
    // excluded from the recommended top-up). Others debit later, so in-flight still needs funding.
    'debits_float_at_submit' => ['payoneer' => true, 'grey' => true, 'stripe_global' => false, 'manual_external' => false],

    // Minor-unit digits per currency (ISO 4217). Anything not listed uses `default`. The admin can
    // override single currencies in Admin -> Payouts -> Controls without a deploy.
    'currency_decimals' => [
        'default' => 2,
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
    ],

    // Provider-facing reference shape (D-3.14). Lower-case letters, digits and '-' are accepted by every
    // rail we integrate; per-provider overrides go here once a provider's real limits are confirmed.
    'reference' => ['max_length' => 32, 'providers' => []],

    // Sandbox/live separation (Addendum D-3.16). `sandbox` is the safe default everywhere. `live` is only honoured when
    // APP_ENV=production; a live provider key is refused outside live mode and a test key is refused inside it.
    'env' => env('PAYOUT_ENV', 'sandbox'),

    // Dedicated, rotatable key for the destination blind index (falls back to APP_KEY). Rotate with
    // `php artisan payouts:reindex-fingerprints`.
    'fp_key' => env('PAYOUT_FP_KEY'),

    'radar' => [
        'spike_factor' => 1.6,
        'fx_buffer_pct' => 3.0,
        'default_weekly_withdraw_ratio' => 0.25, // used until 4 weeks of history exist (low confidence)
        'min_history_weeks' => 4,
        'concentration_alert_pct' => 20.0,
        'velocity_spike_multiple' => 3.0,
        'snapshot_stale_minutes' => 15,
    ],
];
