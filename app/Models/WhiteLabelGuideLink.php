<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Owner request (2026-09-15) — an admin-editable reference link surfaced in
 * the in-app Merchant White Label Guide. `category` matches the hosting
 * choices already used by the project intake form (`own_vps`/`own_shared`)
 * plus a `domain` category (relevant regardless of hosting choice) and a
 * `general` catch-all — so the guide can show only the links relevant to
 * whatever a merchant actually picked.
 *
 * `url` is deliberately free-text rather than validated against a fixed
 * provider list: the whole point is that an admin can swap it for their own
 * affiliate link at any time with no code change.
 */
class WhiteLabelGuideLink extends Model
{
    public const CATEGORY_DOMAIN = 'domain';

    public const CATEGORY_VPS = 'vps';

    public const CATEGORY_SHARED = 'shared';

    public const CATEGORY_GENERAL = 'general';

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        self::CATEGORY_DOMAIN => 'Domain registration',
        self::CATEGORY_VPS => 'Own VPS hosting',
        self::CATEGORY_SHARED => 'Own shared hosting',
        self::CATEGORY_GENERAL => 'General reference',
    ];

    protected $fillable = [
        'category', 'label', 'url', 'description', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::CATEGORY_LABELS);
    }

    /** Active links for one category, in display order — what the merchant
     *  guide actually renders. */
    public static function forCategory(string $category)
    {
        return self::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
