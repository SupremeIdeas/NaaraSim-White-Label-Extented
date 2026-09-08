<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed 3D bento icons (owner request). Each product card — the homepage
 * showcase cards and the paired action tiles — has ONE illustrated icon plus a
 * background opacity, both changeable in Admin → Bento icons with no redeploy.
 *
 * Two visual groups:
 *  - "showcase": the icon sits as a background watermark in the bottom-right of
 *    the card (behind fully-opaque text), so opacity is meaningful (default 80%).
 *  - "tile": the icon sits in the foreground of a compact action tile, so it
 *    shows at full strength (default 100%); the opacity control still applies.
 *
 * Values are stored as settings (bento.icon.{key} = URL, bento.opacity.{key} =
 * 20–100) and cached. Falls back to the shipped /icons/*.webp so a fresh install
 * looks right out of the box.
 */
class BentoIcons
{
    private const CACHE_KEY = 'bento.icons';

    /** Scale clamp (multiplier on the shipped icon size). */
    private const SCALE_MIN = 0.5;

    private const SCALE_MAX = 3.0;

    /**
     * The fixed set of cards, in admin display order. `scale` is the shipped
     * default size multiplier — the action tiles ship BOLD (1.6×) so the 3D
     * icons read premium out of the box; every card stays admin-adjustable.
     */
    public const CARDS = [
        // Homepage showcase cards — 3D icon as a background watermark.
        'esim-data-plans' => ['label' => 'eSIM Data Plans', 'group' => 'showcase', 'file' => 'esim-data-plans.webp', 'opacity' => 80, 'scale' => 1.0],
        'naara-connect' => ['label' => 'Naara Connect', 'group' => 'showcase', 'file' => 'naara-connect.webp', 'opacity' => 80, 'scale' => 1.0],
        'verification-numbers' => ['label' => 'Verification Numbers', 'group' => 'showcase', 'file' => 'verification-numbers.webp', 'opacity' => 80, 'scale' => 1.0],
        'virtual-numbers' => ['label' => 'Virtual Numbers', 'group' => 'showcase', 'file' => 'virtual-numbers.webp', 'opacity' => 80, 'scale' => 1.0],
        'naara-gift' => ['label' => 'Naara Gift', 'group' => 'showcase', 'file' => 'naara-gift.webp', 'opacity' => 80, 'scale' => 1.0],
        // Action tiles — 3D icon in the foreground of a compact card (ship bold).
        'browse-by-country' => ['label' => 'Browse by country', 'group' => 'tile', 'file' => 'browse-by-country.webp', 'opacity' => 100, 'scale' => 1.6],
        'check-compatibility' => ['label' => 'Check compatibility', 'group' => 'tile', 'file' => 'check-compatibility.webp', 'opacity' => 100, 'scale' => 1.6],
        'buy-esim' => ['label' => 'Buy eSIM', 'group' => 'tile', 'file' => 'buy-esim.webp', 'opacity' => 100, 'scale' => 1.6],
        'get-number' => ['label' => 'Get number', 'group' => 'tile', 'file' => 'get-number.webp', 'opacity' => 100, 'scale' => 1.6],
    ];

    /** The shipped default icon URL for a card key (public/icons/*.webp). */
    public static function defaultIcon(string $key): ?string
    {
        $file = self::CARDS[$key]['file'] ?? null;

        return $file ? '/icons/'.$file : null;
    }

    /**
     * The resolved icon+opacity for every card, cached. Each entry is
     * ['icon' => url, 'opacity' => int(20..100)].
     *
     * @return array<string, array{icon: ?string, opacity: int}>
     */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $out = [];
            foreach (self::CARDS as $key => $def) {
                try {
                    $url = (string) Setting::getValue('bento.icon.'.$key, '');
                    $op = Setting::getValue('bento.opacity.'.$key, null);
                    $sc = Setting::getValue('bento.scale.'.$key, null);
                } catch (\Throwable) {
                    $url = '';
                    $op = null;
                    $sc = null;
                }
                $out[$key] = [
                    'icon' => $url !== '' ? $url : self::defaultIcon($key),
                    'opacity' => self::clampOpacity($op, $def['opacity']),
                    'scale' => self::clampScale($sc, $def['scale']),
                ];
            }

            return $out;
        });
    }

    /** Display URL for a card's icon (admin upload or the shipped default). */
    public static function icon(string $key): ?string
    {
        return self::current()[$key]['icon'] ?? self::defaultIcon($key);
    }

    /** The card's opacity as a whole percent (20–100). */
    public static function opacity(string $key): int
    {
        return self::current()[$key]['opacity'] ?? (self::CARDS[$key]['opacity'] ?? 100);
    }

    /** The card's opacity as a 0.20–1.00 fraction, ready for a style attribute. */
    public static function opacityFraction(string $key): float
    {
        return round(self::opacity($key) / 100, 2);
    }

    /** The card's size multiplier (0.5–3.0; admin override or shipped default). */
    public static function scale(string $key): float
    {
        return self::current()[$key]['scale'] ?? (self::CARDS[$key]['scale'] ?? 1.0);
    }

    public static function group(string $key): string
    {
        return self::CARDS[$key]['group'] ?? 'tile';
    }

    /** Clamp a stored opacity to the 20–100 range, else the card default. */
    private static function clampOpacity(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(20, min(100, (int) round((float) $value)));
    }

    /** Clamp a stored scale to the 0.5–3.0 range, else the card default. */
    private static function clampScale(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return round(max(self::SCALE_MIN, min(self::SCALE_MAX, (float) $value)), 2);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isBentoKey(string $key): bool
    {
        return str_starts_with($key, 'bento.');
    }
}
