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

    'product_identifier' => env('NAARA_UPDATE_PRODUCT', 'naarasim-core'),

];
