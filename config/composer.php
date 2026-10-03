<?php

/**
 * Chat Composer Pro (<naara-composer>) rollout flags, one per surface. When a flag is off the surface renders its previous composer
 * markup (kept in resources/views/livewire/partials/legacy-*) so a regression can be rolled back with an env change, no deploy.
 */
return [
    'surfaces' => [
        'support_chat' => (bool) env('COMPOSER_SUPPORT_CHAT', true),
        'send_message' => (bool) env('COMPOSER_SEND_MESSAGE', true),
    ],
];
