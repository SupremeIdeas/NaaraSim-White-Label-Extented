<?php

namespace App\Support\Blog;

/**
 * Builds the share links for one article from config/blog_share.php. Pure (no I/O) so it is trivially testable: every value is
 * URL-encoded once, the canonical article URL always rides along, and UTM tags are appended per channel.
 */
final class ShareLinks
{
    /**
     * @param  list<string>|null  $enabled  channel keys in display order (null = the config default)
     * @return list<array{key:string,label:string,icon:string,href:string,popup:bool}>
     */
    public static function for(string $canonicalUrl, string $title, string $text = '', string $image = '', ?array $enabled = null): array
    {
        $all = (array) config('blog_share.channels', []);
        $keys = $enabled ?? (array) config('blog_share.default_enabled', []);
        $out = [];
        foreach ($keys as $key) {
            if (! isset($all[$key])) {
                continue;
            }
            $c = $all[$key];
            $url = self::withUtm($canonicalUrl, $key);
            $href = strtr((string) $c['href'], [
                '{url}' => rawurlencode($url),
                '{title}' => rawurlencode($title),
                '{text}' => rawurlencode($text !== '' ? $text : $title),
                '{image}' => rawurlencode($image),
            ]);
            $out[] = ['key' => $key, 'label' => (string) $c['label'], 'icon' => (string) $c['icon'], 'href' => $href, 'popup' => (bool) ($c['popup'] ?? true)];
        }

        return $out;
    }

    public static function withUtm(string $url, string $channel): string
    {
        $q = array_merge((array) config('blog_share.utm', []), ['utm_medium' => $channel]);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}
