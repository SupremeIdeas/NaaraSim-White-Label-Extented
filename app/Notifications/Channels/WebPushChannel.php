<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWebPushJob;
use App\Support\WebPushConfig;
use Illuminate\Notifications\Notification;

/**
 * Custom Laravel notification channel for self-hosted web push (owner request).
 * Any notification that lists 'webpush' in via() and uses the InApp trait is
 * pushed to the user's browsers (closed-tab included) — reusing the exact same
 * title/body/icon/url the bell already shows, so there's one payload to maintain.
 *
 * No-ops silently when VAPID isn't configured, so the feature stays dark until
 * the operator generates keys.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! \App\Support\FeatureFlags::enabled('naara_push') || ! isset($notifiable->id)) {
            return;
        }

        // Reuse the in-app payload (title/body/icon/action_url) the bell uses.
        $data = method_exists($notification, 'toArray')
            ? $notification->toArray($notifiable)
            : [];

        if (empty($data['title'])) {
            return;
        }

        SendWebPushJob::dispatch((int) $notifiable->id, [
            'title' => $data['title'],
            'body' => $data['body'] ?? '',
            // OS notifications need a real image URL — use the brand favicon.
            'icon' => \App\Support\BrandSettings::favicon() ?: url('/favicon.ico'),
            'url' => $data['action_url'] ?? url('/notifications'),
        ]);
    }
}
