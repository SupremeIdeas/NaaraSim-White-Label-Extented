<?php

namespace App\Support;

/**
 * Extracts the human-readable COPY out of a page-section config blob (and splices
 * rewritten copy back in), leaving everything else — image URLs, CTA targets,
 * icons, layout toggles, colours, ratings — byte-for-byte untouched. This is what
 * makes the marketing copy populator safe: the AI only ever sees and rewrites
 * prose, never a link or a structural key it could corrupt.
 *
 * Copy is identified by exclusion: any string value whose key is not a known
 * non-copy key and which doesn't look like a URL / route / hex colour. Paths are
 * dot-encoded ("headline", "cards.0.title", "items.1.a") so a flat map round-trips
 * back into the nested config exactly.
 */
class CopyFields
{
    /** Keys whose string values are never copy (links, assets, enums, toggles). */
    private const NON_COPY_KEYS = [
        'image', 'image_url', 'image_side', 'images', 'src', 'photo', 'logo', 'logos',
        'icon', 'badge', 'bg', 'background', 'layout', 'style', 'tone', 'variant',
        'preset', 'mode', 'align', 'side', 'columns', 'color', 'colour', 'rating',
        'cta_target', 'target', 'see_all_target', 'url', 'href', 'link', 'video',
        'embed', 'provider', 'type', 'code', 'html', 'lang', 'enabled',
    ];

    /** @return array<string, string> path => copy text */
    public static function extract(array $config, string $prefix = ''): array
    {
        $out = [];
        foreach ($config as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out += self::extract($value, $path);
                continue;
            }
            if (self::isCopy((string) $key, $value)) {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }

    /** Splice a path => newtext map back onto a clone of $config. */
    public static function apply(array $config, array $byPath): array
    {
        foreach ($byPath as $path => $text) {
            if (! is_string($text)) {
                continue;
            }
            $keys = explode('.', $path);
            $ref = &$config;
            $ok = true;
            foreach ($keys as $k) {
                // Only follow paths that already exist — never create new keys, so
                // a hallucinated path can't inject anything.
                if (is_array($ref) && array_key_exists($k, $ref)) {
                    $ref = &$ref[$k];
                } else {
                    $ok = false;
                    break;
                }
            }
            if ($ok && is_string($ref)) {
                $ref = $text;
            }
            unset($ref);
        }

        return $config;
    }

    private static function isCopy(string $key, mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        if (in_array(strtolower($key), self::NON_COPY_KEYS, true)) {
            return false;
        }
        $v = trim($value);
        // Looks like a link / asset path / hex colour / bare route name → not copy.
        if (preg_match('#^(https?:)?//#i', $v) || str_starts_with($v, '/') || str_contains($v, '://')) {
            return false;
        }
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $v)) {
            return false;
        }

        return true;
    }
}
