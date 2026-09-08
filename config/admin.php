<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin URL path (blueprint Section 25)
    |--------------------------------------------------------------------------
    |
    | The admin panel is mounted under this path. Change ADMIN_PATH per
    | deployment so the admin surface has no guessable URL — the default
    | "adminmaster" should be overridden in production. There are zero links
    | to it from the user-facing UI.
    |
    */

    'path' => env('ADMIN_PATH', 'adminmaster'),

    /*
    |--------------------------------------------------------------------------
    | IP allow-list (optional)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of IPs permitted to reach the admin area. When set,
    | every other IP gets a plain 404 — it never learns the admin panel exists.
    | Leave blank to allow any IP (role + 2FA still apply).
    |
    */

    'ip_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_IP_ALLOWLIST', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Enforce two-factor authentication
    |--------------------------------------------------------------------------
    |
    | When true, an admin without a confirmed TOTP secret is redirected to the
    | admin security page to enrol before any other admin page will open.
    |
    */

    // Admin two-factor is OPT-IN — password + email is enough by default, and a
    // super-admin can require TOTP as an extra layer from the admin Security page
    // (stored as the security.admin_2fa_required setting, which overrides this).
    'require_2fa' => (bool) env('ADMIN_REQUIRE_2FA', false),

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed against the admin area, keyed per admin
    | identity (falls back to IP). Blunts brute-force probing of the path.
    |
    */

    'throttle' => (int) env('ADMIN_THROTTLE', 60),

];
