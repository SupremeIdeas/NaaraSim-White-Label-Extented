<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-configurable persona + knowledge for the NaaraCare agent (Module 24).
 * The operator sets the agent's human name, tone/persona, and extra
 * platform-specific knowledge from the admin panel; the agent grounds every
 * answer on it. Cached; busted on any `support.*` setting save.
 */
class SupportSettings
{
    private const CACHE_KEY = 'support.settings';

    public const DEFAULT_NAME = 'Nia';

    public const DEFAULT_PERSONA = "You are warm, calm and genuinely helpful, like a friendly human support specialist. You use the customer's name when known, keep replies concise, and never sound robotic.";

    /** How the dashboard greeting shows: an inline card or a dismissable popup. */
    public const GREETING_MODES = ['inline', 'popup'];

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'name' => Setting::getValue('support.agent_name') ?: self::DEFAULT_NAME,
                    'persona' => Setting::getValue('support.persona') ?: self::DEFAULT_PERSONA,
                    'knowledge' => Setting::getValue('support.knowledge') ?: '',
                    // Dedicated assistant avatar (admin PNG/WebP). Kept SEPARATE from
                    // the brand favicon so the helper + greeting read cleanly as a
                    // person, not a cramped site icon.
                    'avatar' => (string) (Setting::getValue('support.avatar') ?: ''),
                    'greeting_enabled' => (bool) (Setting::getValue('support.greeting_enabled') ?? true),
                    'greeting_mode' => in_array(Setting::getValue('support.greeting_mode'), self::GREETING_MODES, true)
                        ? Setting::getValue('support.greeting_mode') : 'inline',
                ];
            } catch (\Throwable) {
                return ['name' => self::DEFAULT_NAME, 'persona' => self::DEFAULT_PERSONA, 'knowledge' => '', 'avatar' => '', 'greeting_enabled' => true, 'greeting_mode' => 'inline'];
            }
        });
    }

    public static function name(): string
    {
        return self::current()['name'];
    }

    /** URL of the admin-set assistant avatar, or '' to fall back to a glyph. */
    public static function avatar(): string
    {
        return self::current()['avatar'];
    }

    public static function greetingEnabled(): bool
    {
        return self::current()['greeting_enabled'];
    }

    public static function greetingMode(): string
    {
        return self::current()['greeting_mode'];
    }

    public static function persona(): string
    {
        return self::current()['persona'];
    }

    public static function knowledge(): string
    {
        return self::current()['knowledge'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isSupportKey(string $key): bool
    {
        return str_starts_with($key, 'support.');
    }
}
