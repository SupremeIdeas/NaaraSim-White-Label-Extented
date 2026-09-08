<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * ElevenLabs Convai voice-agent widget (task #17). Admin-controlled: an enable
 * toggle + a PUBLIC agent id (safe to expose — it is not the secret ElevenLabs
 * API key, which powers spoken support replies and lives in ProviderKeys). The
 * widget is a floating voice assistant; when it is off, the existing WhatsApp /
 * live-chat support launcher is the fallback (owner decision).
 *
 * Nothing external loads until an admin turns this on: the SecurityHeaders
 * middleware only widens the strict CSP for ElevenLabs while `active()` is true,
 * so the default policy stays locked down.
 */
class ConvaiWidget
{
    private const CACHE_KEY = 'convai.widget';

    public const KEYS = ['convai.enabled', 'convai.agent_id', 'convai.placement'];

    public const PLACEMENTS = ['both' => 'Everywhere', 'customer' => 'Signed-in app only', 'marketing' => 'Public site only'];

    /** ElevenLabs origins the widget needs, added to the CSP only when active. */
    public const CSP = [
        'script-src' => ['https://unpkg.com', 'https://*.elevenlabs.io'],
        'connect-src' => ['https://*.elevenlabs.io', 'wss://*.elevenlabs.io'],
        'frame-src' => ['https://*.elevenlabs.io'],
        'font-src' => ['https://*.elevenlabs.io'],
        'worker-src' => ["'self'", 'blob:'],
    ];

    /** @return array{enabled:bool, agent_id:string, placement:string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'enabled' => (bool) Setting::getValue('convai.enabled', false),
                    'agent_id' => self::sanitizeId((string) Setting::getValue('convai.agent_id', '')),
                    'placement' => (string) Setting::getValue('convai.placement', 'both'),
                ];
            } catch (\Throwable) {
                return ['enabled' => false, 'agent_id' => '', 'placement' => 'both'];
            }
        });
    }

    /** True only when the admin enabled it AND set an agent id — the CSP gate. */
    public static function active(): bool
    {
        $c = self::current();

        return $c['enabled'] && $c['agent_id'] !== '';
    }

    public static function agentId(): string
    {
        return self::current()['agent_id'];
    }

    public static function placement(): string
    {
        $p = self::current()['placement'];

        return array_key_exists($p, self::PLACEMENTS) ? $p : 'both';
    }

    /** Should the widget render in this context ('customer' | 'marketing')? */
    public static function shownOn(string $context): bool
    {
        if (! self::active()) {
            return false;
        }
        $p = self::placement();

        return $p === 'both' || $p === $context;
    }

    public static function save(bool $enabled, string $agentId, string $placement): void
    {
        Setting::setValue('convai.enabled', $enabled, 'integrations');
        Setting::setValue('convai.agent_id', self::sanitizeId($agentId), 'integrations');
        Setting::setValue('convai.placement', array_key_exists($placement, self::PLACEMENTS) ? $placement : 'both', 'integrations');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Agent ids are opaque tokens — keep only URL/attribute-safe characters. */
    public static function sanitizeId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', trim($id)) ?? '';
    }
}
