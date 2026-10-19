<?php

/*
| Cookie consent (generic platform feature, ships to every install).
| Categories: `necessary` is always on and needs no consent. The others are opt-in: nothing in them loads or is set until the visitor says yes.
| The services below are the known third parties the platform can load; one is only listed to visitors (and only loads) while its admin setting is on.
*/
return [
    'cookie' => 'naara_consent',
    'categories' => [
        'necessary' => ['label' => 'Strictly necessary', 'locked' => true, 'description' => 'Keeps you signed in, protects forms from forgery, remembers your cookie choice and your language and theme. The site cannot work without these.'],
        'functional' => ['label' => 'Functional', 'locked' => false, 'description' => 'Optional extras from other providers, such as the customer reviews widget and embedded videos. Without them those parts stay hidden or ask first.'],
        'analytics' => ['label' => 'Analytics', 'locked' => false, 'description' => 'Helps us see which pages are used and where people get stuck, in aggregate, so we can improve the product.'],
        'marketing' => ['label' => 'Marketing', 'locked' => false, 'description' => 'Lets advertising partners measure whether our ads work and show relevant ones. Off unless you agree.'],
    ],
    // key => [category, name, provider, purpose, cookies [name, duration], privacy url]
    'services' => [
        'naara_core' => ['necessary', 'Naara session and security', 'Naara', 'Sign-in session, form protection, your cookie choice, language and theme.', [['naarasim_session', 'Session'], ['XSRF-TOKEN', 'Session'], ['naara_consent', '6 months'], ['naara_ref', '30 days']], null],
        'google_analytics' => ['analytics', 'Google Analytics 4', 'Google', 'Counts visits and page views so we can improve the product.', [['_ga', '13 months'], ['_ga_*', '13 months']], 'https://policies.google.com/privacy'],
        'meta_pixel' => ['marketing', 'Meta Pixel', 'Meta', 'Measures how well our adverts on Facebook and Instagram work.', [['_fbp', '3 months'], ['_fbc', '3 months']], 'https://www.facebook.com/privacy/policy/'],
        'trustpilot' => ['functional', 'Trustpilot reviews', 'Trustpilot', 'Shows verified customer reviews on our pages.', [['(set by Trustpilot)', 'Per Trustpilot']], 'https://legal.trustpilot.com/for-reviewers/privacy-policy'],
    ],
    'defaults' => [
        'title' => 'Your privacy, your choice',
        'body' => 'We use cookies to keep Naara working, and, only if you agree, to understand how it is used and to measure our adverts. You can change your mind at any time.',
        'accept' => 'Accept all', 'reject' => 'Reject all', 'customise' => 'Choose',
        'layout' => 'card',            // card (corner) | bar (full width) | modal (centre, dims the page)
        'position' => 'right',         // left | right (card only)
        'theme' => 'skin',             // skin (follows the member's skin and dark mode) | navy | teal | light | custom
        'custom' => ['bg' => '#0D1B2A', 'text' => '#F1F5F9', 'button' => '#84CC16', 'button_text' => '#0D1B2A'],
        'renew_days' => 180,
        'retention_months' => 36,
        'respect_gpc' => true,
        'show_reopen' => true,
    ],
];
