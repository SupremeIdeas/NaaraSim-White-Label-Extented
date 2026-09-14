<?php

return [
    // get-number.blade.php — active order card
    'your_number' => 'Your number',
    'code' => 'Code',
    'waiting_for_code' => 'Waiting for the code…',
    'done' => 'Done',
    'send_sms' => 'Send an SMS',
    'view_on_dashboard' => 'View on dashboard',

    // numbers-modals.blade.php — shared modal chrome
    'modal_title' => [
        'verify' => 'Naara Verify',
        'rent' => 'Naara Rent',
        'line' => 'Naara Line',
        'default' => 'Numbers',
    ],
    'back_to_numbers' => 'Back to Numbers',
    'close' => 'Close',

    // shared across verify/rent step partials
    'taxes_and_fees' => 'Taxes & fees',
    'you_pay' => 'You pay',
    'priced_at_reservation' => 'Priced at reservation',
    'use_naaracredits' => 'Use my NaaraCredits',
    'credits_balance' => 'You have :balance credits. Apply :credits to save :amount on this order.',
    'reserving' => 'Reserving…',
    'country_label' => 'Country',
    'service_label' => 'Service',

    // verify.blade.php
    'verify' => [
        'reserved_title' => 'Number reserved',
        'reserved_body' => 'Your verification number is ready — we’re fetching the code now.',
        'buy_another' => 'Buy another',
        'manual_buy' => 'Manual Buy',
        'smart_buy' => 'Smart Buy',
        'smart_buy_hint' => 'Pick a service — we choose the best-priced country and network for you automatically.',
        'networks' => 'Networks',
        'prices_tab' => 'Prices',
        'stats_tab' => 'Statistics',
        'export_csv' => 'Export CSV',
        'auto_best_network' => 'Auto — best network',
        'recommended' => 'Recommended',
        'best' => 'Best',
        'out_of_stock' => 'Out of stock',
        'available_short' => ':count avail.',
        'get_my_code' => 'Get my code',
        'refund_guarantee' => 'No code within :minutes minutes → automatic refund to your wallet, no ticket required.',
    ],

    // rent.blade.php
    'rent' => [
        'reserved_title' => 'Rental reserved',
        'reserved_body' => 'Your rental number is ready — it receives SMS for its rental period.',
        'rent_another' => 'Rent another',
        'single_service' => 'Single service',
        'any_service' => 'Any service',
        'soon' => '(soon)',
        'any_service_note' => 'One number, :bold — receives SMS from every service for the rental period.',
        'any_service_bold' => 'any service',
        'rental_length' => 'Rental length',
        'auto_renew' => 'Auto-renew when it expires',
        'short_term_note' => 'A short-term rental — receives SMS for a fixed period. For a longer rental, choose a US number.',
        'rent_this_number' => 'Rent this number',
        'wallet_note' => 'Charged from your wallet. Auto-refund if unavailable.',
    ],

    // line.blade.php
    'line' => [
        'mobile_numbers' => 'Mobile numbers',
        'toll_free' => 'Toll-free',
        'coming_soon_title' => 'Naara Line is coming soon',
        'coming_soon_body' => 'A permanent international number with voice & SMS — we’re finishing the last checks with our carrier.',
        'intro' => 'A permanent international number that’s yours to keep — :bold, billed monthly.',
        'intro_bold' => 'voice calls and SMS',
        'vanity_label' => 'Find a memorable number (optional)',
        'vanity_placeholder' => 'e.g. 777 or ends with 0000',
        'active_title' => 'Naara Line active',
        'active_hint' => 'Set up forwarding or the dialer from your dashboard.',
        'monthly_note' => 'Naara Line is a monthly subscription — the first month is charged now, then it renews monthly. Voice & SMS included.',
        'mobile_fallback' => 'Mobile',
        'get_this_number' => 'Get this number',
        'search_again' => 'Search again',
        'search_available' => 'Search available numbers',
        'searching' => 'Searching…',
    ],
];
