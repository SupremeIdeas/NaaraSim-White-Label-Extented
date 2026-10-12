<?php

/**
 * Guided tours, as code (Engineering v3 Phase 10). Seeded into the `tours` / `tour_steps` tables by TourRegistry::sync() and editable in
 * Admin > Guided tours; a tour an admin has edited is never overwritten by a code change (it can be reset to these defaults).
 *
 * A step may only describe something that exists on the page. `anchor` is a `data-tour` value on a real element (see the Tour Integrity check:
 * a step whose anchor is not in any view fails the build). `requires`: `optional` = skip silently when the element is not on this person's page
 * (e.g. Gift cards when the feature is off); `route:<name>` = skip unless the route exists. Everything else that is missing is logged.
 * `viewport`: all | mobile | desktop. `before_click`: another anchor to click first (opens a tab). `quick` = included in the ~30-second replay.
 *
 * Master-only tours (Naara Chat, Business Suite) live in config/tours_n2n.php and config/tours_business_suite.php, which only exist in the master repo.
 */
return [
    'window_days' => 30,   // new-user window; 0 = never expires
    'hub_visibility_after_window' => 'hide',   // hide | keep   (owner intent still to confirm; one switch either way)

    'tours' => [
        'dashboard.main' => [
            'section' => 'Dashboard', 'title' => 'Your dashboard', 'summary' => 'Find eSIMs, numbers, your wallet and notifications.', 'icon' => 'home',
            'entry_route' => 'dashboard', 'audience' => 'all', 'sort' => 10,
            'steps' => [
                ['anchor' => 'nav.catalogue', 'title' => 'eSIM data plans', 'body' => 'Browse data plans for 190+ countries and install one in minutes. No swaps, no roaming bills.', 'quick' => true],
                ['anchor' => 'nav.numbers', 'title' => 'Numbers and messages', 'body' => 'Get a number for calls and texts, or a one-time number to receive a verification code.', 'quick' => true],
                ['anchor' => 'nav.gift-cards', 'title' => 'Gift cards', 'body' => 'Buy a gift card with your wallet balance.', 'requires' => 'optional'],
                ['anchor' => 'nav.wallet', 'title' => 'Your wallet', 'body' => 'One balance pays for everything. Top it up, withdraw earnings and see every transaction here.', 'quick' => true],
                ['anchor' => 'nav.more', 'title' => 'The More menu', 'body' => 'Rewards, support, security and the rest of your account live in More.', 'viewport' => 'mobile'],
                ['anchor' => 'nav.rewards', 'title' => 'Rewards', 'body' => 'Check in daily and earn credit you can spend or withdraw.', 'viewport' => 'desktop', 'requires' => 'optional'],
                ['anchor' => 'header.bell', 'title' => 'Notifications', 'body' => 'Orders, payouts and security notices land here. Choose what reaches you in Notification settings.', 'quick' => true],
            ],
        ],
        'wallet.main' => [
            'section' => 'Wallet', 'title' => 'Your wallet', 'summary' => 'Add funds, withdraw and track spending.', 'icon' => 'wallet',
            'entry_route' => 'wallet', 'audience' => 'all', 'sort' => 20,
            'steps' => [
                ['anchor' => 'wallet.balance', 'title' => 'Your balance', 'body' => 'Your spendable balance is in US dollars, with a live local equivalent underneath.', 'quick' => true],
                ['anchor' => 'wallet.topup', 'title' => 'Add funds', 'body' => 'Top up with a card, bank transfer or crypto. The credit lands as soon as the payment is confirmed.', 'quick' => true],
                ['anchor' => 'wallet.payout', 'title' => 'Withdraw earnings', 'body' => 'Send earnings to a bank account you have saved and verified.', 'quick' => true],
                ['anchor' => 'wallet.tabs', 'title' => 'Four tabs', 'body' => 'Top Up, Payout, Spending and Shared, each with its own page.'],
                ['anchor' => 'wallet.tab-shared', 'title' => 'Shared wallet', 'body' => 'Invite someone to spend from your balance, with an optional limit for each person.', 'before_click' => 'wallet.tab-shared'],
                ['anchor' => 'wallet.history', 'title' => 'History and spending', 'body' => 'This month\'s spending, a 14-day chart and your recent transactions.', 'before_click' => 'wallet.tab-spending'],
            ],
        ],
    ],
];
