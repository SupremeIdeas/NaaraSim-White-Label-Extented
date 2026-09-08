<?php

namespace App\Models;

use App\Models\Concerns\HasReactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Blog post (Module 30). Draft until published; only published posts (with a
 * past published_at) are ever public. SEO fields fall back to the title/excerpt.
 */
class Post extends Model
{
    use HasReactions;

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body', 'cover_image_url', 'accent_color',
        'meta_title', 'meta_description', 'status', 'published_at', 'author_id',
    ];

    /**
     * The accent colour driving the scroll-tint (Blog overhaul §5). Graceful
     * fallback: the admin-set colour, else a stable colour derived from the
     * category, else the brand teal — so it always looks intentional.
     */
    public function accentColor(): string
    {
        if ($this->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $this->accent_color)) {
            return $this->accent_color;
        }

        // Deterministic pleasant hue from the category name (HSL → hex).
        $cat = trim((string) $this->category);
        if ($cat === '') {
            return '#0A6E6E';
        }
        $hue = crc32(mb_strtolower($cat)) % 360;

        return self::hslToHex($hue, 55, 42);
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Publicly visible: published and past its publish time. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $n = 1;
        while (self::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->meta_description ?: Str::limit(strip_tags((string) $this->excerpt), 155);
    }
}
