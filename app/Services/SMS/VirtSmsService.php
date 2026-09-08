<?php

namespace App\Services\SMS;

/**
 * VirtSMS — a second SMS-Activate-protocol provider, the FALLBACK in the OTP/
 * rental "full rent" lane behind HeroSMS. Same handler_api.php protocol, so it
 * reuses HeroSmsService's implementation wholesale and only swaps the config
 * key (services.virtsms.*) and label. Key-gated: reports itself unavailable
 * until VIRTSMS_API_KEY is set, so the router stays cleanly within the lane.
 */
class VirtSmsService extends HeroSmsService
{
    protected function providerKey(): string
    {
        return 'virtsms';
    }

    protected function label(): string
    {
        return 'VirtSMS';
    }
}
