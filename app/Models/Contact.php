<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved contact in the user's in-app address book (Live Voice — Part C). Feeds
 * the dialer's destination field. Auth-scoped — a contact only ever belongs to
 * the user who created it.
 */
class Contact extends Model
{
    protected $fillable = ['user_id', 'name', 'phone_number', 'is_favorite'];

    protected function casts(): array
    {
        return ['is_favorite' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Up-to-2-letter initials for the avatar (no photo field exists). */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $words = array_values(array_filter($words));
        if ($words === []) {
            return '#';
        }
        $first = mb_substr($words[0], 0, 1);
        $second = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';

        return mb_strtoupper($first.$second);
    }

    /**
     * A deterministic gradient (hex from→to) for the initials avatar, so a
     * contact always gets the same colour. Inline hex — NOT Tailwind classes —
     * because the colour is chosen at runtime and JIT would purge dynamic
     * class names. Brand-leaning palette.
     *
     * @return array{from: string, to: string}
     */
    public function avatarColor(): array
    {
        $palette = [
            ['#2dd4bf', '#0d9488'], // teal
            ['#38bdf8', '#2563eb'], // sky→blue
            ['#a78bfa', '#7c3aed'], // violet
            ['#fbbf24', '#ea580c'], // amber→orange
            ['#fb7185', '#db2777'], // rose→pink
            ['#34d399', '#16a34a'], // emerald→green
            ['#22d3ee', '#0d9488'], // cyan→teal
            ['#818cf8', '#4f46e5'], // indigo
        ];
        [$from, $to] = $palette[crc32(mb_strtolower(trim($this->name))) % count($palette)];

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Normalise a raw phone string to a dialable form: keep a single leading '+'
     * and the digits, drop spaces / dashes / parens. Non-E.164 input is left for
     * the dialer to validate at call time (the user can edit it in the field).
     */
    public static function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        $plus = str_starts_with($raw, '+') ? '+' : '';

        return $plus.preg_replace('/\D+/', '', $raw);
    }
}
