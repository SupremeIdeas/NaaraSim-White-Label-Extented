<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Update package trust
    |--------------------------------------------------------------------------
    |
    | NaaraSim update packages (`.naaraupdate`) are signed with an Ed25519
    | PRIVATE key that lives ONLY on the machine/CI that builds packages — it
    | must never be present inside a deployed application (a server compromise
    | must not be able to sign its own "update"). Every deployed instance,
    | original and every white-label copy, carries only the PUBLIC verification
    | key below and uses it to reject any tampered or forged package before a
    | single payload file is touched.
    |
    | Generate a fresh keypair with `php artisan update:keygen`, paste the
    | public half here (via .env), and keep the private half out of git.
    |
    */

    'public_key' => env('NAARA_UPDATE_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Where built packages land
    |--------------------------------------------------------------------------
    |
    | `update:package` writes the finished `.naaraupdate` artifact here. This is
    | a build-side concern on the publisher (the original NaaraSim); a
    | white-label instance never builds packages, it only verifies and (from
    | Batch 2/5) applies them.
    |
    */

    'package_storage_path' => storage_path('app/update-packages'),

    /*
    |--------------------------------------------------------------------------
    | Product identifier
    |--------------------------------------------------------------------------
    |
    | Stamped into every manifest this instance builds and checked by the
    | verifier. Set once, at fork time:
    |   - the master platform (original NaaraSim) stays `naarasim-core`
    |   - a white-label fork sets NAARA_UPDATE_PRODUCT=naarasim-whitelabel
    | so a package built for one product line can't be silently applied to the
    | other.
    |
    */

    'product_identifier' => env('NAARA_UPDATE_PRODUCT', 'naarasim-whitelabel'),

    /*
    |--------------------------------------------------------------------------
    | Distribution client (white-label subscriber only — Updater Batch 5)
    |--------------------------------------------------------------------------
    |
    | Set at registration time (Batch 6's license-issuance flow): the master
    | platform's own base URL, and this instance's Sanctum API token, scoped to
    | updates.check/download + themes.check/download (Batch 4 §1). The token is
    | deliberately narrow — a registered-but-unpaid instance can still receive
    | core platform updates without that being tangled up in which product
    | features it's paid to unlock (Batch 5 §5).
    |
    | Never in the database, never in a committed config file — .env only.
    |
    */

    'original_platform_base_url' => env('NAARA_ORIGINAL_PLATFORM_URL'),

    'api_token' => env('NAARA_UPDATE_API_TOKEN'),

];
