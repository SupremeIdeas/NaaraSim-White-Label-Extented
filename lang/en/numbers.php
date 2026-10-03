<?php

return [
    // get-number.blade.php — active order card
    'your_number' => 'Your number',
    'code' => 'Code',
    'waiting_for_code' => 'Waiting for the code…',
    'done' => 'Done',
    'send_sms' => 'Send an SMS',
    'view_on_dashboard' => 'View on dashboard',

    // numbers-modals.blade.php — shared modal chrome. Rebranded at the source
    // (owner audit, 2026-09-15) so every consumer gets the white-label word
    // for free, matching ProviderModels::brandize()'s existing convention.
    'modal_title' => [
        'verify' => \App\Support\BrandSettings::rebrand('Naara Verify'),
        'rent' => \App\Support\BrandSettings::rebrand('Naara Rent'),
        'line' => \App\Support\BrandSettings::rebrand('Naara Line'),
        'default' => 'Numbers',
    ],
    'back_to_numbers' => 'Back to Numbers',
    'close' => 'Close',

    // active-line strip + landing heading
    'your_naara' => \App\Support\BrandSettings::rebrand('Your Naara'),
    'line_active' => 'Active',
    'manage' => 'Manage',
    'top_up' => 'Top up',
    'what_today' => 'What do you need today?',
    'what_today_sub' => 'Choose a service and get started in seconds.',

    // shared across verify/rent step partials
    'taxes_and_fees' => 'Taxes & fees',
    'you_pay' => 'You pay',
    'priced_at_reservation' => 'Priced at reservation',
    'use_naaracredits' => \App\Support\BrandSettings::rebrand('Use my NaaraCredits'),
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
        'banner_title' => \App\Support\BrandSettings::rebrand('Naara Verify'),
        'banner_text' => 'Secure your network. Verify your identity.',
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
        'step_country' => '1. Select country',
        'step_country_hint' => 'Choose the country for your number',
        'step_service' => '2. Select service',
        'step_service_hint' => 'What will you use this number for?',
        'step_length_hint' => 'How long do you need the number for?',
        'why_title' => 'Why rent a number?',
        'why_text' => 'Keep your personal number private, verify accounts and reach global services.',
        'auto_renew' => 'Auto-renew when it expires',
        'short_term_note' => 'A short-term rental — receives SMS for a fixed period. For a longer rental, choose a US number.',
        'rent_this_number' => 'Rent this number',
        'wallet_note' => 'Charged from your wallet. Auto-refund if unavailable.',
    ],

    // line.blade.php
    'line' => [
        'mobile_numbers' => 'Mobile numbers',
        'toll_free' => 'Toll-free',
        'coming_soon_title' => \App\Support\BrandSettings::rebrand('Naara Line is coming soon'),
        'coming_soon_body' => 'A permanent international number with voice & SMS — we’re finishing the last checks with our carrier.',
        'intro' => 'A permanent international number that’s yours to keep — :bold, billed monthly.',
        'intro_bold' => 'voice calls and SMS',
        'vanity_label' => 'Find a memorable number (optional)',
        'vanity_placeholder' => 'e.g. 777 or ends with 0000',
        'active_title' => \App\Support\BrandSettings::rebrand('Naara Line active'),
        'active_hint' => 'Set up forwarding or the dialer from your dashboard.',
        'active_hint_sms_only' => 'This number supports SMS only — voice calls and call forwarding aren’t available on it.',
        'monthly_note' => \App\Support\BrandSettings::rebrand('Naara Line is a monthly subscription — the first month is charged now, then it renews monthly. Voice & SMS included.'),
        'mobile_fallback' => 'Mobile',
        'get_this_number' => 'Get this number',
        'search_again' => 'Search again',
        'search_available' => 'Search available numbers',
        'searching' => 'Searching…',
    ],

    // my-lines.blade.php — port-in premium card
    'portin_card' => [
        'kicker' => 'Port-in · US & Canada',
        'title' => 'Bring your number to Naara',
        'text' => 'Already have a US or Canada number? Keep it. We run the carrier transfer for you.',
        'cta' => 'Start transfer',
        'chip_keep' => 'Keep your number',
        'chip_days' => '5–15 business days',
        'chip_voice' => 'Voice + SMS',
    ],
];
