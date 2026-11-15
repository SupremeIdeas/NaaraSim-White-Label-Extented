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
];
