<?php

/**
 * Notification categories (a trimmed copy of master's catalog: this install has no Bootstrap tooling, so only the category defaults live here).
 * `critical` = security or money-at-risk: the bell copy can never be switched off. `default_on` applies until the person chooses otherwise.
 */

return [
    'categories' => [
        'security' => ['label' => 'Security and account', 'critical' => true, 'consent' => false, 'default_on' => true],
        'billing' => ['label' => 'Orders, receipts and billing', 'critical' => true, 'consent' => false, 'default_on' => true],
        'services' => ['label' => 'Your numbers, eSIMs and calls', 'critical' => false, 'consent' => false, 'default_on' => true],
        'lifecycle' => ['label' => 'Getting started and goals', 'critical' => false, 'consent' => false, 'default_on' => true],
        'support' => ['label' => 'Support', 'critical' => false, 'consent' => false, 'default_on' => true],
        'marketing' => ['label' => 'Offers and announcements', 'critical' => false, 'consent' => true, 'default_on' => false],
        'admin' => ['label' => 'Admin alerts (operators only)', 'critical' => true, 'consent' => false, 'default_on' => true],
    ],
];
