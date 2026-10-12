<?php

/**
 * Blog share channels. One config drives the Share modal, the server-side URL builder and the progressive-enhancement link list.
 *
 * Placeholders: {url} {title} {text} {image}. Every value is URL-encoded by App\Support\Blog\ShareLinks.
 * `verified` is the date the endpoint was last checked against the platform's own documentation. null = NOT yet checked live; the
 * `naara:verify blog` check (Phase 11) lists these so the owner can re-check them. Endpoints change, so do not assume.
 */
return [
    'utm' => ['utm_source' => 'share', 'utm_campaign' => 'blog'],

    'channels' => [
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'whatsapp', 'href' => 'https://wa.me/?text={text}%20{url}', 'verified' => null, 'popup' => true],
        'facebook' => ['label' => 'Facebook', 'icon' => 'facebook', 'href' => 'https://www.facebook.com/sharer/sharer.php?u={url}', 'verified' => null, 'popup' => true],
        'x' => ['label' => 'X', 'icon' => 'x', 'href' => 'https://x.com/intent/post?url={url}&text={text}', 'verified' => null, 'popup' => true],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'linkedin', 'href' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}', 'verified' => null, 'popup' => true],
        'telegram' => ['label' => 'Telegram', 'icon' => 'telegram', 'href' => 'https://t.me/share/url?url={url}&text={text}', 'verified' => null, 'popup' => true],
        'reddit' => ['label' => 'Reddit', 'icon' => 'reddit', 'href' => 'https://www.reddit.com/submit?url={url}&title={title}', 'verified' => null, 'popup' => true],
        'pinterest' => ['label' => 'Pinterest', 'icon' => 'pinterest', 'href' => 'https://www.pinterest.com/pin/create/button/?url={url}&media={image}&description={text}', 'verified' => null, 'popup' => true],
        'email' => ['label' => 'Email', 'icon' => 'mail', 'href' => 'mailto:?subject={title}&body={text}%0A%0A{url}', 'verified' => '2026-10-12', 'popup' => false],
    ],

    /** Shown (in this order) until the admin saves their own list. */
    'default_enabled' => ['whatsapp', 'facebook', 'x', 'linkedin', 'telegram', 'email'],
];
