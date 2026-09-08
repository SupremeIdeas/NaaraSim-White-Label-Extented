<?php

namespace App\Support;

/**
 * Small accessor for the web-push (VAPID) configuration. The public key is the
 * only piece the browser needs to subscribe; the private key stays server-side.
 * If keys aren't configured, the whole feature stays dark (no opt-in shown, no
 * jobs dispatched) so nothing half-works.
 */
class WebPushConfig
{
    public static function configured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public static function publicKey(): string
    {
        return (string) config('webpush.vapid.public_key');
    }
}
