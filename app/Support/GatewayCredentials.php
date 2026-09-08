<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Dual sandbox/live payment-gateway credentials (HOTFIX §4).
 *
 * Before this, there was ONE key field per gateway, reused for both sandbox and
 * live — the operator had to hand-swap the pasted key to match the mode toggle,
 * and there was nowhere to store a public/publishable key at all (blocking
 * inline/embedded checkout). This stores TWO key sets (sandbox + live) per card
 * gateway, plus a public key for each, and makes the existing Sandbox/Live
 * toggle actually select which stored set is active.
 *
 * Scope: the three prefix-keyed card gateways (Paystack, Flutterwave, Stripe) —
 * the ones whose sandbox/live is told apart by key prefix AND that have a real
 * client-side inline flow needing a public key. Host-based gateways (PayPal,
 * NOWPayments) already separate environments via PaymentGatewayConfig's host
 * swap, and secret-only gateways have no client-side flow, so neither needs a
 * public-key field (money-rule 2 discipline: don't add fields nothing uses).
 *
 * ADDITIVE + safe: applyToConfig() only overlays config('services.{gw}.{type}')
 * when a dual value is actually set, so a gateway configured the old way keeps
 * working untouched until the operator fills in the new fields. It runs AFTER
 * ProviderKeys so, once set, the mode-appropriate key wins.
 */
class GatewayCredentials
{
    private const SETTING = 'payments.credentials';

    private const MIGRATED = 'payments.credentials.migrated.v1';

    /** gateway => key types stored per mode. */
    public const GATEWAYS = [
        'paystack' => ['secret_key', 'public_key'],
        'flutterwave' => ['secret_key', 'public_key'],
        'stripe' => ['secret_key', 'public_key'],
    ];

    public const MODES = ['sandbox', 'live'];

    /** @return array<string, array<string, array<string, string>>> gw => mode => type => value */
    public static function all(): array
    {
        try {
            $stored = Setting::getValue(self::SETTING, []);

            return is_array($stored) ? $stored : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function get(string $gateway, string $mode, string $type): string
    {
        return (string) (self::all()[$gateway][$mode][$type] ?? '');
    }

    /**
     * Persist the full credential matrix (from the admin form) and re-apply so it
     * takes effect this request.
     *
     * @param  array<string, array<string, array<string, string>>>  $data
     */
    public static function saveAll(array $data): void
    {
        $clean = [];
        foreach (self::GATEWAYS as $gw => $types) {
            foreach (self::MODES as $mode) {
                foreach ($types as $type) {
                    $val = trim((string) ($data[$gw][$mode][$type] ?? ''));
                    if ($val !== '') {
                        $clean[$gw][$mode][$type] = $val;
                    }
                }
            }
        }
        Setting::setValue(self::SETTING, $clean, 'payments', 'Dual sandbox/live gateway credentials (encrypted).');
        self::applyToConfig();
    }

    /**
     * Overlay the mode-appropriate stored key onto config() for each gateway, so
     * every gateway service keeps reading config('services.{gw}.secret_key') and
     * the new public_key. Only overlays a value that is actually set (additive).
     */
    public static function applyToConfig(): void
    {
        // Runs at boot — degrade silently if the settings table isn't there yet
        // (pre-install), mirroring the other boot overlays.
        try {
            $all = self::all();
            foreach (self::GATEWAYS as $gw => $types) {
                $mode = PaymentGatewayConfig::mode($gw); // sandbox | live
                foreach ($types as $type) {
                    $val = (string) ($all[$gw][$mode][$type] ?? '');
                    if ($val !== '') {
                        config(["services.{$gw}.{$type}" => $val]);
                    }
                }
            }
        } catch (\Throwable) {
            // no-op until the settings table exists
        }
    }

    /**
     * One-time seed of the dual store from any legacy single key already
     * configured (via ProviderKeys / env), placing it in the slot that matches
     * PaymentSandbox's test-prefix detection — so an already-configured gateway
     * shows up correctly in the new UI and the toggle becomes meaningful. Never
     * overwrites a value the operator has already set in the new store.
     */
    public static function migrateLegacy(): void
    {
        try {
            if (Setting::getValue(self::MIGRATED, false)) {
                return;
            }
        } catch (\Throwable) {
            return; // pre-install / no settings table — nothing to migrate
        }

        try {
            $store = self::all();
            foreach (self::GATEWAYS as $gw => $types) {
                // Skip a gateway the operator already populated in the new store.
                if (! empty($store[$gw])) {
                    continue;
                }
                $secret = (string) config("services.{$gw}.secret_key", '');
                if ($secret === '') {
                    continue;
                }
                $mode = PaymentSandbox::isTest($gw) ? 'sandbox' : 'live';
                $store[$gw][$mode]['secret_key'] = $secret;
                $public = (string) config("services.{$gw}.public_key", '');
                if ($public !== '') {
                    $store[$gw][$mode]['public_key'] = $public;
                }
            }

            Setting::setValue(self::SETTING, $store, 'payments');
            Setting::setValue(self::MIGRATED, true, 'payments');
        } catch (\Throwable) {
            // Settings table not ready — safe to retry next time the page opens.
        }
    }

    /** A masked preview of a stored key (never echo the full secret to the UI). */
    public static function preview(string $gateway, string $mode, string $type): ?string
    {
        $v = self::get($gateway, $mode, $type);
        if ($v === '') {
            return null;
        }
        $len = strlen($v);

        return $len <= 8 ? str_repeat('•', $len) : substr($v, 0, 4).str_repeat('•', min($len - 8, 10)).substr($v, -4);
    }

    /**
     * Merge newly-typed values over the stored matrix (blank = leave unchanged),
     * then persist. Used by the admin form so secrets are never round-tripped
     * through the browser — only new values are sent.
     *
     * @param  array<string, array<string, array<string, string>>>  $typed
     */
    public static function updateFrom(array $typed): void
    {
        $merged = self::all();
        foreach (self::GATEWAYS as $gw => $types) {
            foreach (self::MODES as $mode) {
                foreach ($types as $type) {
                    $val = trim((string) ($typed[$gw][$mode][$type] ?? ''));
                    if ($val !== '') {
                        $merged[$gw][$mode][$type] = $val;
                    }
                }
            }
        }
        self::saveAll($merged);
    }

    public static function isCredentialKey(string $key): bool
    {
        return $key === self::SETTING;
    }
}
