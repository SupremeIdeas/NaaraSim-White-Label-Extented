<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support & contact (blueprint Section 32)
    |--------------------------------------------------------------------------
    |
    | WhatsApp number (international format, digits only) and support email
    | surfaced to customers for live help. Leave WhatsApp blank to hide the
    | button.
    |
    */

    'support' => [
        'whatsapp' => env('SUPPORT_WHATSAPP', ''),          // e.g. 2348012345678
        'email' => env('SUPPORT_EMAIL', 'support@naarasim.com'),
        'whatsapp_message' => env('SUPPORT_WHATSAPP_MESSAGE', 'Hi NaaraSim, I need help with'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Autopilot (BUILD-4 §7)
    |--------------------------------------------------------------------------
    |
    | Automated, opt-in, template-based WhatsApp notifications for lifecycle
    | events. The master switch below lets an operator disable Autopilot even
    | when the Cloud API keys are live. Each event maps to the operator's own
    | Meta-approved template name (the names below are sensible defaults) — a
    | blank template name disables that one event without touching the others.
    |
    */

    'whatsapp_autopilot' => [
        'enabled' => env('WHATSAPP_AUTOPILOT_ENABLED', true),
        'templates' => [
            'esim_delivered' => env('WHATSAPP_TPL_ESIM_DELIVERED', 'esim_delivered'),
            'number_delivered' => env('WHATSAPP_TPL_NUMBER_DELIVERED', 'number_delivered'),
            'renewal_reminder' => env('WHATSAPP_TPL_RENEWAL_REMINDER', 'renewal_reminder'),
            'low_balance' => env('WHATSAPP_TPL_LOW_BALANCE', 'low_balance'),
            'order_failed' => env('WHATSAPP_TPL_ORDER_FAILED', 'order_failed'),
        ],
    ],

];
