<?php

namespace App\Services\Payouts\Guardian;

/**
 * Fuzzy person-name comparison for gate G7: case, accents, punctuation and word
 * order are ignored ("Okafor, Chinedu" == "Chinedu Okafor"); a missing middle name
 * costs little, a different surname costs a lot.
 */
final class NameMatcher
{
    /** Does this string look like a person/business name rather than an email, id or address? */
    public static function looksLikeName(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && ! str_contains($value, '@') && ! preg_match('/\d{4,}/', $value)
            && count(self::tokens($value)) >= 1 && preg_match('/\p{L}{2,}/u', $value) === 1;
    }

    /** 0.0 – 1.0 */
    public static function score(string $a, string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        // Token-set overlap, with near-miss tokens (typos, abbreviations) credited partially.
        $matched = 0.0;
        $pool = $tb;
        foreach ($ta as $token) {
            $best = 0.0;
            $bestKey = null;
            foreach ($pool as $k => $other) {
                $s = self::tokenSimilarity($token, $other);
                if ($s > $best) {
                    $best = $s;
                    $bestKey = $k;
                }
            }
            if ($bestKey !== null && $best >= 0.8) {
                $matched += $best;
                unset($pool[$bestKey]);
            }
        }

        $setScore = $matched / max(count($ta), count($tb));

        // A missing middle name costs little: when every token of the shorter (>= 2-token)
        // name is matched, that is a strong match even though the other has extras.
        $shorter = min(count($ta), count($tb));
        if ($shorter >= 2 && $matched >= $shorter - 0.2) {
            $setScore = max($setScore, 0.9);
        }

        // Order-insensitive whole-string similarity as a floor for small spelling differences.
        sort($ta);
        sort($tb);
        similar_text(implode(' ', $ta), implode(' ', $tb), $pct);

        return round(max($setScore, $pct / 100 * 0.95), 4);
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $name = mb_strtolower(trim($name));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = $ascii !== false && $ascii !== '' ? $ascii : $name;
        $name = preg_replace('/[^a-z\s]/u', ' ', $name) ?? '';

        return array_values(array_filter(preg_split('/\s+/', $name) ?: [], fn ($t) => strlen($t) > 1));
    }

    private static function tokenSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        // "Chinedu" vs "C" style initials were dropped by tokens(); handle prefixes (Chuks/Chukwuemeka are NOT matched on purpose).
        $lev = levenshtein($a, $b);

        return max(0.0, 1 - $lev / max(strlen($a), strlen($b)));
    }
}
