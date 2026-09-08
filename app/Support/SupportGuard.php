<?php

namespace App\Support;

/**
 * Data-scoping guardrail for the NaaraCare AI agent (Module 24). The agent's
 * tools are already written to read ONLY the current user's records, but this is
 * the defense-in-depth layer: it strips any private cost/profit/secret key from
 * every value handed to the model, so a coding slip can never leak business
 * economics or another party's data into a customer-facing answer.
 *
 * Mirrors the money-safety "never expose cost" rule (blueprint 1.2 / rule 2) and
 * the SecretGuard used by the maintenance loop.
 */
class SupportGuard
{
    /** Keys that must never reach the model / the user. */
    private const FORBIDDEN_KEYS = [
        'cost_price_usd', 'wholesale_cost', 'provider_cost', 'net_price',
        'profit', 'margin', 'markup_pct', 'airalo_min_price', 'monthly_cost',
        'min_price', 'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'api_key', 'secret', 'secret_key',
        'client_secret', 'auth_token', 'webhook_secret',
    ];

    /**
     * Recursively remove forbidden keys from an array (case-insensitive, matches
     * a key that contains a forbidden token, e.g. "provider_cost_usd").
     */
    public static function scrub(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isForbidden($key)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::scrub($value) : $value;
        }

        return $clean;
    }

    public static function isForbidden(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            if (str_contains($key, $forbidden)) {
                return true;
            }
        }

        return false;
    }
}
