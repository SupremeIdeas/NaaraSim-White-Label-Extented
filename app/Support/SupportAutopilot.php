<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\Cache;

/**
 * Autopilot policy for the NaaraCare agent (owner request). The AI may RESOLVE a
 * ticket itself — but only within a tightly bounded allowlist. This class is the
 * single source of truth for what "trusted" means:
 *
 *   - A master switch (support.autopilot.enabled). Off => the agent can only
 *     diagnose and escalate, never act.
 *   - A goodwill ceiling (support.autopilot.goodwill_cap_usd, default 0). This is
 *     the ONE money lever the AI may pull, and it is deliberately small and
 *     admin-set. 0 disables goodwill entirely (the agent must escalate instead).
 *
 * Critical actions — refunds, account changes, deletions, pricing, provider keys,
 * anything touching another user — have NO tool at all. The only path to them is
 * escalate_to_human. That is the "some critical areas need staff/admin oversight"
 * rule, enforced by omission rather than trust.
 *
 * Every autopilot action is recorded here (audit log + a note on the ticket) so
 * staff always see exactly what the AI did on their behalf.
 */
class SupportAutopilot
{
    private const CACHE_KEY = 'support.autopilot.v1';

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'enabled' => (bool) Setting::getValue('support.autopilot.enabled', true),
                    'goodwill_cap_usd' => round((float) Setting::getValue('support.autopilot.goodwill_cap_usd', 0), 2),
                ];
            } catch (\Throwable) {
                return ['enabled' => true, 'goodwill_cap_usd' => 0.0];
            }
        });
    }

    public static function enabled(): bool
    {
        return (bool) self::all()['enabled'];
    }

    /** The most goodwill (USD) the AI may grant in a single action. 0 = disabled. */
    public static function goodwillCapUsd(): float
    {
        return (float) self::all()['goodwill_cap_usd'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isAutopilotKey(string $key): bool
    {
        return str_starts_with($key, 'support.autopilot.');
    }

    /**
     * Record an autopilot action: an audit-log row plus an appended note on the
     * conversation so staff can see the AI's actions inline. Best-effort — a
     * logging hiccup must never break the customer's answer.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(SupportConversation $conversation, string $action, array $context = []): void
    {
        try {
            Auditor::log("support.autopilot.$action", SupportConversation::class, $conversation->id, $context);

            $log = $conversation->autopilot_log ?? [];
            $log[] = ['action' => $action, 'at' => now()->toIso8601String()] + $context;
            $conversation->forceFill(['autopilot_log' => $log])->save();
        } catch (\Throwable) {
            // best-effort audit trail
        }
    }
}
