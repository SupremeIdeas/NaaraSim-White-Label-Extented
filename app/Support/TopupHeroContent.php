<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The Top-Up hero: a title + line, an illustration (SVG, shipped), and a slidable carousel of offer banners the admin uploads
 * (Admin > Top-ups > Hero & banners). Until the admin uploads their own, the four banners shipped for the Numbers hero are used
 * (public/img/topup/banner-*.webp), so the carousel is never empty.
 */
class TopupHeroContent
{
    private const CACHE = 'topup.hero.v1';

    public const MAX_SLIDES = 10;

    public const DEFAULTS = [
        'title' => 'Top up any phone, anywhere.',
        'description' => 'Airtime and data in seconds. We find the network from the number, and show every offer for that line.',
        'enabled' => true,
        'autoplay' => true,
        'interval' => 5,
        'slides' => [],   // the simple hero stands alone; an admin can add their own banners (the shipped ones carry the Naara mark)
    ];

    /** @return array{title: string, description: string, enabled: bool, autoplay: bool, interval: int, slides: list<array{image: string, title: string, link: string}>} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $s = (array) Setting::getValue('topup.hero', []);
            } catch (\Throwable) {
                $s = [];
            }
            $slides = isset($s['slides']) && is_array($s['slides']) && $s['slides'] !== [] ? array_values($s['slides']) : self::DEFAULTS['slides'];

            return [
                'title' => (string) ($s['title'] ?? '') !== '' ? (string) $s['title'] : self::DEFAULTS['title'],
                'description' => (string) ($s['description'] ?? '') !== '' ? (string) $s['description'] : self::DEFAULTS['description'],
                'enabled' => (bool) ($s['enabled'] ?? true),
                'autoplay' => (bool) ($s['autoplay'] ?? true),
                'interval' => max(2, min(30, (int) ($s['interval'] ?? 5))),
                'slides' => array_slice(array_map(fn ($x) => ['image' => (string) ($x['image'] ?? ''), 'title' => (string) ($x['title'] ?? ''), 'link' => (string) ($x['link'] ?? '')], $slides), 0, self::MAX_SLIDES),
            ];
        });
    }

    public static function save(array $data): void
    {
        Setting::setValue('topup.hero', $data, 'topup');
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }
}
