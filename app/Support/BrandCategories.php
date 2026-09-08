<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Business category taxonomy for the Brand Directory (BUILD-9 §7). A researched,
 * creator/small-business-sized list. Admin-extensible via a Setting — this is
 * NOT permanently fixed in code. "Other" always exists so onboarding never
 * blocks on a missing category.
 */
class BrandCategories
{
    /** @var list<string> */
    public const DEFAULTS = [
        'Fashion & Apparel', 'Beauty & Skincare', 'Food & Beverage', 'Restaurants & Cafés',
        'Fitness & Wellness', 'Health & Nutrition', 'Music & Artists', 'Comedy & Entertainment',
        'Film & Media Production', 'Photography & Videography', 'Gaming & Esports', 'Tech & Apps',
        'Finance & Fintech', 'Real Estate & Property', 'Travel & Tourism', 'Automotive',
        'Education & Coaching', 'Business & Entrepreneurship', 'Fashion Retail & E-commerce',
        'Home & Interior Design', 'Beauty & Hair Services', 'Events & Nightlife',
        'Sports & Athletics', 'Parenting & Family', 'Nonprofit & Community', 'Faith & Spirituality',
        'Art & Design', 'Podcasts & Talk Shows', 'News & Commentary', 'Other',
    ];

    /** @return list<string> the default list plus any admin-added categories, "Other" last. */
    public static function all(): array
    {
        try {
            $extra = Setting::getValue('brand.categories', []);
        } catch (\Throwable) {
            $extra = [];
        }
        $extra = is_array($extra) ? array_map('strval', $extra) : [];

        $list = array_values(array_unique(array_merge(self::DEFAULTS, $extra)));
        // Keep "Other" at the very end.
        $list = array_values(array_filter($list, fn ($c) => $c !== 'Other'));
        $list[] = 'Other';

        return $list;
    }

    public static function isValid(string $category): bool
    {
        return in_array($category, self::all(), true);
    }
}
