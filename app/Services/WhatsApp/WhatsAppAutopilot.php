<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppTemplateJob;
use App\Models\User;

/**
 * WhatsApp Autopilot (BUILD-4 §7). The high-level entry point the rest of the
 * app calls after a lifecycle event ("eSIM delivered", "renewal due", …). It
 * decides — quietly and safely — whether a WhatsApp message should go out and
 * queues it; it never sends synchronously and never throws into the caller.
 *
 * A message is sent ONLY when ALL of these hold, so we never spam or breach
 * Meta's opt-in policy:
 *   1. Autopilot is switched on (config) AND the Cloud API keys are live.
 *   2. The event maps to a non-blank, operator-approved template name.
 *   3. The user has explicitly opted in (users.whatsapp_opt_in).
 *   4. We have a WhatsApp number for them (whatsapp_number, else phone).
 *
 * Cost is never involved here (notifications only), so this touches no money
 * rule beyond "external calls are queued" — which the job enforces.
 */
class WhatsAppAutopilot
{
    public function __construct(private WhatsAppCloudClient $client) {}

    /** Master gate: on by config AND keys present. */
    public function enabled(): bool
    {
        return (bool) config('naara.whatsapp_autopilot.enabled', true)
            && $this->client->configured();
    }

    /** The Meta-approved template name mapped to an event, or null if disabled. */
    public function templateFor(string $event): ?string
    {
        $name = trim((string) config("naara.whatsapp_autopilot.templates.{$event}", ''));

        return $name !== '' ? $name : null;
    }

    /** A user is reachable when they opted in AND we have a number for them. */
    public function reachable(User $user): bool
    {
        return (bool) $user->whatsapp_opt_in && $this->numberFor($user) !== null;
    }

    public function numberFor(User $user): ?string
    {
        $raw = (string) ($user->whatsapp_number ?: $user->phone ?: '');
        $digits = preg_replace('/\D+/', '', $raw);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Queue an Autopilot notification for a lifecycle event. Returns true when a
     * message was queued, false when any gate blocked it (the common, silent
     * path). $bodyParams fill the template's {{1}}, {{2}}, … placeholders.
     *
     * @param  list<string|int|float>  $bodyParams
     */
    public function notify(User $user, string $event, array $bodyParams = [], ?string $lang = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $template = $this->templateFor($event);
        if ($template === null || ! $this->reachable($user)) {
            return false;
        }

        SendWhatsAppTemplateJob::dispatch(
            toE164: $this->numberFor($user),
            template: $template,
            lang: $lang,
            components: WhatsAppCloudClient::bodyComponents($bodyParams),
        );

        return true;
    }
}
