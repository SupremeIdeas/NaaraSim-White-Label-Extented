<?php

namespace App\Notifications\Concerns;

/**
 * Shared in-app (bell / notification centre) behaviour for notifications
 * (owner request). A notification that uses this trait declares one method,
 * inApp(), returning the on-screen payload; the trait wires it into Laravel's
 * `database` channel via toArray(). Delivery to both channels is then just:
 *
 *     public function via($n): array { return ['mail', 'database']; }
 *
 * The payload is intentionally small and presentation-only — never a secret,
 * cost, or another user's data (money-safety rule 2 still applies here).
 *
 * Standard shape:
 *   category      one of: order | wallet | support | security | offer | system
 *   icon          an SVG sprite name (UI rule: SVG only, no emoji)
 *   title, body   short human copy
 *   action_url    optional in-app destination the user can act on
 *   action_label  optional CTA label for that destination
 */
trait InApp
{
    /**
     * The on-screen payload. Each notification implements this.
     *
     * @return array{category:string, icon:string, title:string, body:string, action_url?:?string, action_label?:?string}
     */
    abstract public function inApp(object $notifiable): array;

    /** Laravel `database` channel storage — the bell reads this. */
    public function toArray(object $notifiable): array
    {
        $payload = $this->inApp($notifiable);

        return [
            'category' => $payload['category'] ?? 'system',
            'icon' => $payload['icon'] ?? 'bell',
            'title' => $payload['title'] ?? '',
            'body' => $payload['body'] ?? '',
            'action_url' => $payload['action_url'] ?? null,
            'action_label' => $payload['action_label'] ?? null,
        ];
    }
}
