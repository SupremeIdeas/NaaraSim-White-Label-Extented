<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID keys (self-hosted web push)
    |--------------------------------------------------------------------------
    | Generate a pair once with `php artisan webpush:vapid` and paste them into
    | .env. The PUBLIC key is safe to expose to the browser; the PRIVATE key is
    | a secret and must never reach the client (money-safety rule 10 — secrets
    | in .env via config(), never in code). `subject` is a mailto: or https URL
    | identifying the sender, per the VAPID spec.
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://naarasim.com')),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],
];
