<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single reaction by one user on one reactable subject (blog post today).
 * The set of reaction types is fixed (never user free-text) and each maps to a
 * sprite glyph — NOT an emoji character, per the platform's emoji-free UI rule.
 */
class Reaction extends Model
{
    public const UPDATED_AT = null; // created_at only

    protected $fillable = ['reactable_type', 'reactable_id', 'user_id', 'type'];

    /** type => [label, sprite-icon]. Order is the display order. */
    public const TYPES = [
        'like' => ['Like', 'thumbs-up'],
        'love' => ['Love', 'heart'],
        'celebrate' => ['Celebrate', 'party'],
        'insightful' => ['Insightful', 'bulb'],
        'smile' => ['Made me smile', 'smile'],
    ];

    public static function isValidType(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type][0] ?? ucfirst($type);
    }

    public static function icon(string $type): string
    {
        return self::TYPES[$type][1] ?? 'star';
    }

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
