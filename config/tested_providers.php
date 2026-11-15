<?php

/**
 * Providers that were exercised with REAL API keys against their live endpoints, so a white-label client can start on a rail that is already proven.
 * Honest by construction: every entry says what was tested and what was not, and nothing here is a claim about a provider nobody has called.
 *
 *   level  proven     a real business flow was exercised live (catalogue, rates, corridors, offers): shown as "Recommended" plus "Tested live"
 *          retest     optional: the latest re-test and anything that did not pass, shown next to the badge so it never overstates
 *          connected  the credentials were accepted by the live API and nothing more yet: shown as "Tested live" only
 *
 * Add a provider here only after a real call has succeeded, with the date and the exact scope. The badge, the Provider Keys page, the Integrations
 * hub and Payout health all read this one file.
 */
return [
    'grey' => ['level' => 'proven', 'on' => '2026-11-15', 'tested' => 'Production API: balances, FX rates, payout-methods (32 corridors synced), credentials probe.', 'not_yet' => 'A real payout: the Grey wallets are unfunded.'],
    'esimgo' => ['level' => 'proven', 'on' => '2026-11-15', 'tested' => 'Live catalogue sync (about 14,000 plans) priced through our engine, balance lookup.', 'not_yet' => 'A real order: no eSIM was bought.'],
    'paga' => ['level' => 'proven', 'on' => '2026-11', 'tested' => 'Live probes and airtime/data offer sync, operator lookup by number.', 'not_yet' => 'A real top-up: no money was spent.', 'retest' => '2026-11-15 from the build sandbox: HTTP 401 "device fails secondary credential check" (Paga allow-lists the calling IP). Re-test from the production server.'],
    'ding' => ['level' => 'proven', 'on' => '2026-11', 'tested' => 'Live probes and airtime/data offer sync, operator lookup by number.', 'not_yet' => 'A real top-up: the DingConnect balance is empty.', 'retest' => '2026-11-15 from the build sandbox: HTTP 401 AuthenticationFailed (key rotated or the calling IP is not allowed). Re-test from the production server.'],
    'quibity' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Balance lookup: the live API accepted the credentials.', 'not_yet' => 'Catalogue sync and orders.'],
    'twilio' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Account lookup: the live API accepted the credentials.', 'not_yet' => 'Calls, SMS and number purchase.'],
    'dojah' => ['level' => 'connected', 'on' => '2026-11', 'tested' => 'Credentials authenticate; failure and empty-wallet outcomes classified.', 'not_yet' => 'NIN and CAC lookups: the Dojah wallet is empty.'],
    'paystack' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Live secret key accepted by the Paystack API (read-only call).', 'not_yet' => 'A real charge, transfer or webhook round-trip.'],
    'nowpayments' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API key accepted by NOWPayments (status and currency list).', 'not_yet' => 'A real payment and a signed IPN delivery.'],
    'zendit' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Credentials accepted by the Zendit API (read-only call).', 'not_yet' => 'Offer sync and purchases.'],
    'telnyx' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API key accepted by Telnyx (account lookup).', 'not_yet' => 'Number search, purchase and voice.'],
    'getatext' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API key accepted by Getatext (read-only call).', 'not_yet' => 'A real number rental and SMS receipt.'],
    'fivesim' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API key accepted by 5sim (profile and price lookup).', 'not_yet' => 'A real activation purchase.'],
    'bitrefill' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API credentials accepted by Bitrefill (read-only call).', 'not_yet' => 'Product sync and orders.'],
    'africastalking' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Sandbox credentials accepted by the Africa\'s Talking sandbox host.', 'not_yet' => 'Production keys and a real SMS.'],
    'fincra' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'Test-mode keys accepted by Fincra.', 'not_yet' => 'Live keys and a real payout.'],
    'giphy' => ['level' => 'connected', 'on' => '2026-11-15', 'tested' => 'API key accepted by Giphy (search call).', 'not_yet' => 'Nothing money-related; chat GIF picker only.'],
];
