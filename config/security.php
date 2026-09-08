<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy (blueprint Section 30)
    |--------------------------------------------------------------------------
    |
    | Tuned to work with the TALL stack: no external script origins (the main
    | XSS-injection defense), object-src none, and locked base-uri/form-action/
    | frame-ancestors. 'unsafe-eval' is required by Alpine (bundled with
    | Livewire) and 'unsafe-inline' by the pre-paint theme script + inline
    | styles. Override SECURITY_CSP to tighten (e.g. a nonce-based policy) later.
    |
    */

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'policy' => env('SECURITY_CSP', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "connect-src 'self'",
            // Homepage video section (BUILD-3 §8): self-hosted clips on the cloud
            // disk (https) or a local blob, and the privacy-friendly YouTube
            // embed. Turnstile appends its own origin to this frame-src at runtime.
            "media-src 'self' https: blob:",
            "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com",
        ])),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Sent only over HTTPS. One year, subdomains included.
    |
    */

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSRF allow-list
    |--------------------------------------------------------------------------
    |
    | Hostnames the app may fetch server-side even though the SsrfGuard blocks
    | private/reserved ranges by default. Comma-separated. Usually empty — the
    | private-range block is the primary control.
    |
    */

    'ssrf_allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SSRF_ALLOWED_HOSTS', ''))
    ))),

];
