<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest (blueprint Section 18.2). Stores structured
            // config (incl. provider keys) as an encrypted JSON blob. Values
            // set via setValue() are wrapped as ['value' => ...] so scalars
            // and arrays alike round-trip through the array cast.
            'value' => 'encrypted:array',
            'is_public' => 'boolean',
        ];
    }

    /** Cache TTL for resolved settings, in seconds (1h — blueprint 3.1). */
    private const CACHE_TTL = 3600;

    private static function cacheKey(string $key): string
    {
        return "setting:$key";
    }

    /**
     * Resolve a setting value by key, falling back to $default when unset.
     * This is the single accessor the PricingEngine (and everything else)
     * uses to read config — never read the column directly.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $resolved = Cache::remember(self::cacheKey($key), self::CACHE_TTL, function () use ($key) {
            $setting = static::query()->where('key', $key)->first();

            // Sentinel so a genuine null value is distinguishable from "missing".
            if ($setting === null) {
                return ['__missing__' => true];
            }

            return ['value' => $setting->value['value'] ?? null];
        });

        if (isset($resolved['__missing__'])) {
            return $default;
        }

        return $resolved['value'];
    }

    /**
     * Create or update a setting, then bust its cache. Value is stored wrapped
     * so scalars survive the encrypted:array cast.
     */
    public static function setValue(string $key, mixed $value, ?string $group = null, ?string $description = null, bool $isPublic = false): static
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => ['value' => $value],
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ], fn ($v) => $v !== null),
        );

        Cache::forget(self::cacheKey($key));

        return $setting;
    }

    protected static function booted(): void
    {
        // Keep the resolver cache honest if a row is edited/removed directly.
        static::saved(fn (Setting $s) => Cache::forget(self::cacheKey($s->key)));
        static::deleted(fn (Setting $s) => Cache::forget(self::cacheKey($s->key)));
    }
}
