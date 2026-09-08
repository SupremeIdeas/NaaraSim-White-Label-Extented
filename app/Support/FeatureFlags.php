<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Feature toggles (owner request). An EXTRA control layer on top of the
 * key-driven "Coming Soon" strategy: the admin can switch a whole feature on or
 * off from the panel, independently of whether its API keys are set.
 *
 * A feature is EFFECTIVELY enabled only when BOTH are true:
 *   - the admin has it switched on (Setting `features.<key>.enabled`), and
 *   - its requirements are met (all required config keys present) — or it needs
 *     no keys at all.
 *
 * So an admin can pre-disable a feature they're not ready for, and a feature
 * whose keys aren't in yet stays "Coming Soon" even when switched on. Feature
 * code asks FeatureFlags::enabled('naara_push') and nothing half-runs.
 */
class FeatureFlags
{
    private const CACHE_PREFIX = 'features.enabled.';

    /**
     * The toggleable features. `requires` lists config keys that must all be
     * non-empty for the feature to actually run; `guide` names an in-panel
     * setup guide; `default` is the shipped on/off.
     */
    public const FEATURES = [
        'naara_push' => [
            'name' => 'Naara Push',
            'group' => 'Notifications',
            'icon' => 'bell',
            'description' => 'Browser push notifications that reach users even when the tab or app is closed. Self-hosted (VAPID) — no third-party push service.',
            'requires' => ['webpush.vapid.public_key', 'webpush.vapid.private_key'],
            'guide' => 'webpush',
            'default' => true,
        ],
        'in_app_notifications' => [
            'name' => 'In-app notifications',
            'group' => 'Notifications',
            'icon' => 'bell',
            'description' => 'The notification bell + centre. Orders, wallet, refunds, support replies and offers appear in-app.',
            'requires' => [],
            'guide' => null,
            'default' => true,
        ],
        'ai_support' => [
            'name' => 'NaaraCare AI agent',
            'group' => 'Support',
            'icon' => 'id-card',
            'description' => 'The AI support agent that diagnoses, analyses evidence, and resolves safe tickets on autopilot.',
            'requires' => ['services.anthropic.api_key'],
            'guide' => 'anthropic',
            'default' => true,
        ],
        'voice_replies' => [
            'name' => 'Spoken support replies',
            'group' => 'Support',
            'icon' => 'mic',
            'description' => 'Read support replies aloud to paying customers (ElevenLabs).',
            'requires' => ['services.elevenlabs.api_key'],
            'guide' => 'elevenlabs',
            'default' => true,
        ],
        'naara_gift' => [
            'name' => 'Naara Gift (gift cards)',
            'group' => 'Products',
            'icon' => 'gift',
            'description' => 'The gift-card storefront powered by Reloadly (primary) + Zendit (failover). On by default; still stays "Coming Soon" until the Reloadly keys are added and the catalogue is synced.',
            'requires' => ['services.reloadly.client_id', 'services.reloadly.client_secret'],
            'guide' => null,
            'default' => true,
        ],
    ];

    /** Admin's on/off switch for a feature (ignores whether keys are present). */
    public static function adminEnabled(string $key): bool
    {
        if (! isset(self::FEATURES[$key])) {
            return false;
        }

        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            try {
                $stored = Setting::getValue("features.$key.enabled");
            } catch (\Throwable) {
                $stored = null;
            }

            return $stored === null ? (bool) self::FEATURES[$key]['default'] : (bool) $stored;
        });
    }

    /** Whether the feature's required API keys are all present. */
    public static function configured(string $key): bool
    {
        foreach (self::FEATURES[$key]['requires'] ?? [] as $configKey) {
            if (empty(config($configKey))) {
                return false;
            }
        }

        return true;
    }

    /** Effectively live: switched on AND its keys are present. */
    public static function enabled(string $key): bool
    {
        return self::adminEnabled($key) && self::configured($key);
    }

    public static function setEnabled(string $key, bool $on): void
    {
        if (! isset(self::FEATURES[$key])) {
            return;
        }
        Setting::setValue("features.$key.enabled", $on, 'features');
        self::flush($key);
    }

    /**
     * All features with their live state for the admin panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return collect(self::FEATURES)->map(fn ($meta, $key) => $meta + [
            'key' => $key,
            'admin_enabled' => self::adminEnabled($key),
            'configured' => self::configured($key),
            'enabled' => self::enabled($key),
            'needs_keys' => ! empty($meta['requires']),
        ])->values()->all();
    }

    public static function flush(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget(self::CACHE_PREFIX.$key);

            return;
        }
        foreach (array_keys(self::FEATURES) as $k) {
            Cache::forget(self::CACHE_PREFIX.$k);
        }
    }

    public static function isFeatureKey(string $key): bool
    {
        return str_starts_with($key, 'features.');
    }
}
