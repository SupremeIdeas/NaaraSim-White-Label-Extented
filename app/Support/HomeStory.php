<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Homepage "our story" section (BUILD-3 §9) — an admin-editable, scroll-revealed
 * vision/mission block that sits after the video section and leads, via one
 * deliberate transition line, into the stacking-containers section so the scroll
 * reads as one narrative. Purely additive: when the admin hasn't enabled it (or
 * left the body blank) the section simply doesn't render.
 */
class HomeStory
{
    private const CACHE_KEY = 'home.story.v1';

    public const ENABLED_KEY = 'home.story.enabled';

    public const EYEBROW_KEY = 'home.story.eyebrow';

    public const HEADING_KEY = 'home.story.heading';

    public const BODY_KEY = 'home.story.body';

    public const TRANSITION_KEY = 'home.story.transition';

    public const DEFAULTS = [
        'eyebrow' => 'Our story',
        'heading' => 'Built so no African traveller is ever cut off.',
        'body' => "NaaraSim began with a simple frustration: crossing a border shouldn't mean losing your number, your data, or your peace of mind.\n\nSo we built one place for both — eSIM data for 190+ countries and real phone numbers you keep — for the students, founders, families and travellers moving across the continent and the world.",
        'transition' => 'Here’s how it all fits together.',
    ];

    /** @return array{enabled: bool, eyebrow: string, heading: string, body: string, transition: string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'enabled' => (bool) Setting::getValue(self::ENABLED_KEY, false),
                    'eyebrow' => self::value(self::EYEBROW_KEY, 'eyebrow'),
                    'heading' => self::value(self::HEADING_KEY, 'heading'),
                    'body' => self::value(self::BODY_KEY, 'body'),
                    'transition' => self::value(self::TRANSITION_KEY, 'transition'),
                ];
            } catch (\Throwable) {
                return ['enabled' => false] + self::DEFAULTS;
            }
        });
    }

    private static function value(string $key, string $default): string
    {
        $v = trim((string) Setting::getValue($key, ''));

        return $v !== '' ? $v : self::DEFAULTS[$default];
    }

    /** Only render when the admin enabled it AND there's a body to show. */
    public static function isVisible(): bool
    {
        $c = self::current();

        return $c['enabled'] && trim($c['body']) !== '';
    }

    public static function eyebrow(): string
    {
        return self::current()['eyebrow'];
    }

    public static function heading(): string
    {
        return self::current()['heading'];
    }

    /** @return list<string> body split into paragraphs */
    public static function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', self::current()['body']) ?: [])));
    }

    public static function transition(): string
    {
        return self::current()['transition'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isStoryKey(string $key): bool
    {
        return in_array($key, [self::ENABLED_KEY, self::EYEBROW_KEY, self::HEADING_KEY, self::BODY_KEY, self::TRANSITION_KEY], true);
    }
}
