<?php

namespace App\Support\Niche;

/**
 * Data estimator (blueprint Section 32) — helps a traveller pick the right plan
 * size before buying, so they don't over- or under-purchase. Rough, honest
 * per-day estimates by usage profile; not a guarantee.
 */
class DataEstimator
{
    /** MB per day by usage profile. */
    public const PROFILES = [
        'light' => ['label' => 'Light — maps, chat, email', 'mb_per_day' => 250],
        'medium' => ['label' => 'Medium — social, browsing, some video', 'mb_per_day' => 800],
        'heavy' => ['label' => 'Heavy — lots of video, calls, hotspot', 'mb_per_day' => 2000],
    ];

    /**
     * Estimate total data for a profile over N days.
     *
     * @return array{mb:int,gb:float,label:string}
     */
    public static function estimate(string $profile, int $days): array
    {
        $perDay = self::PROFILES[$profile]['mb_per_day'] ?? self::PROFILES['medium']['mb_per_day'];
        $days = max(1, $days);
        $mb = $perDay * $days;

        return [
            'mb' => $mb,
            'gb' => round($mb / 1024, 1),
            'label' => self::PROFILES[$profile]['label'] ?? '',
        ];
    }

    /** How many days a given GB allowance lasts for a profile. */
    public static function daysFor(float $gb, string $profile): int
    {
        $perDay = self::PROFILES[$profile]['mb_per_day'] ?? self::PROFILES['medium']['mb_per_day'];

        return (int) floor(($gb * 1024) / $perDay);
    }
}
