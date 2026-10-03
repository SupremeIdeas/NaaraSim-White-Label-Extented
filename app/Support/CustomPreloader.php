<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Custom preloader artwork (Preloader Studio → "Your own animation"): an admin uploads an animated GIF, an animated WebP or a Lottie JSON,
 * separately for LIGHT and DARK mode. Everything here is about keeping that safe and light:
 *
 *  - The file is identified by its CONTENT (magic bytes / parsed JSON), never by the extension the browser claims.
 *  - A preloader runs on every page, so the size caps are small (1 MB image, 512 KB Lottie).
 *  - Lottie JSON is parsed and rejected when it carries expressions (the `x` key holds script the player would `eval`) or remote asset URLs
 *    (the player would fetch them), so an uploaded animation can never run code or phone home.
 *  - Files are stored under a random name and, for Lottie, served from OUR origin through a route (the CSP only lets scripts fetch from
 *    'self', and the object store is another origin). GIF/WebP are plain <img> and load straight from storage.
 *  - Animated WebP/GIF never go through the image-compression pass (GD would flatten the animation to one frame).
 */
class CustomPreloader
{
    public const DIR = 'preloaders';

    public const MAX_IMAGE_KB = 1024;

    public const MAX_LOTTIE_KB = 512;

    /** The two modes an animation can be set for. */
    public const VARIANTS = ['light', 'dark'];

    /** What the file input offers. */
    public const ACCEPT = '.gif,.webp,.json,image/gif,image/webp,application/json';

    /**
     * Validate and store an upload. Returns the metadata persisted in the preloader assignment:
     * ['kind' => 'gif'|'webp'|'lottie', 'path' => relative path, 'disk' => disk, 'id' => uuid].
     *
     * @throws \InvalidArgumentException with an admin-readable message
     */
    public static function store(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $size = strlen($bytes);
        $kind = self::sniff($bytes);

        if ($kind === null) {
            throw new \InvalidArgumentException('That file is not a GIF, WebP or Lottie JSON animation.');
        }
        if ($kind === 'lottie') {
            if ($size > self::MAX_LOTTIE_KB * 1024) {
                throw new \InvalidArgumentException('Lottie files must be under '.self::MAX_LOTTIE_KB.' KB — a preloader loads on every page.');
            }
            self::assertSafeLottie($bytes);
        } else {
            if ($size > self::MAX_IMAGE_KB * 1024) {
                throw new \InvalidArgumentException('GIF and WebP files must be under '.(self::MAX_IMAGE_KB / 1024).' MB — a preloader loads on every page.');
            }
            if ($kind === 'webp' && ! self::webpIsAnimated($bytes)) {
                // A still WebP is allowed (it is a perfectly good static mark); only note nothing — it simply will not move.
            }
        }

        $id = (string) Str::uuid();
        $ext = $kind === 'lottie' ? 'json' : $kind;
        $disk = MediaStorage::disk();
        $path = self::DIR.'/'.$id.'.'.$ext;
        Storage::disk($disk)->put($path, $bytes, 'public');

        return ['kind' => $kind, 'path' => $path, 'disk' => $disk, 'id' => $id];
    }

    /** What a file really is, from its bytes: gif | webp | lottie | null. */
    public static function sniff(string $bytes): ?string
    {
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'gif';
        }
        if (strlen($bytes) > 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }
        $head = ltrim(substr($bytes, 0, 64), "\xEF\xBB\xBF \t\r\n");
        if ($head !== '' && $head[0] === '{') {
            $data = json_decode($bytes, true);
            if (is_array($data) && isset($data['layers'], $data['fr'], $data['op'])) {
                return 'lottie';
            }
        }

        return null;
    }

    /** True when the WebP container carries animation (an ANIM chunk). */
    public static function webpIsAnimated(string $bytes): bool
    {
        return str_contains(substr($bytes, 0, 4096), 'ANIM');
    }

    /**
     * Reject Lottie JSON that could execute script or fetch remote files.
     *
     * @throws \InvalidArgumentException
     */
    public static function assertSafeLottie(string $json): void
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('That Lottie file is not valid JSON.');
        }
        foreach (['v', 'fr', 'ip', 'op', 'w', 'h'] as $key) {
            if (! array_key_exists($key, $data) || ! is_numeric($data[$key]) && $key !== 'v') {
                throw new \InvalidArgumentException('That does not look like a Lottie animation (missing "'.$key.'").');
            }
        }
        if (! is_array($data['layers'] ?? null) || $data['layers'] === []) {
            throw new \InvalidArgumentException('That Lottie animation has no layers.');
        }
        self::walk($data, 0);
    }

    private static function walk(array $node, int $depth): void
    {
        if ($depth > 40) {
            throw new \InvalidArgumentException('That Lottie animation is nested too deeply.');
        }
        foreach ($node as $key => $value) {
            // `x` holds an expression (script) in Lottie; plain animations never store a string there.
            if ($key === 'x' && is_string($value)) {
                throw new \InvalidArgumentException('Lottie expressions are not allowed. Export the animation without expressions.');
            }
            if (is_string($value)) {
                // Remote asset URLs the player would fetch (image layers). Embedded (data:) assets are fine.
                if (preg_match('#^\s*(https?:)?//#i', $value) === 1) {
                    throw new \InvalidArgumentException('That Lottie animation loads files from another website. Export it with the images embedded.');
                }
                if (preg_match('#^\s*javascript:#i', $value) === 1) {
                    throw new \InvalidArgumentException('That Lottie animation contains a script link.');
                }
            } elseif (is_array($value)) {
                self::walk($value, $depth + 1);
            }
        }
    }

    /** Where the page loads a stored asset from: the object store for images, our own origin for Lottie JSON. */
    public static function url(array $meta): ?string
    {
        if (empty($meta['path']) || empty($meta['kind'])) {
            return null;
        }
        if ($meta['kind'] === 'lottie') {
            return route('preloader.asset', ['id' => $meta['id'] ?? pathinfo($meta['path'], PATHINFO_FILENAME)]);
        }
        try {
            return Storage::disk($meta['disk'] ?? MediaStorage::disk())->url($meta['path']);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Remove a stored file (best effort — a missing file is fine). */
    public static function delete(?array $meta): void
    {
        if (! $meta || empty($meta['path']) || ! str_starts_with((string) $meta['path'], self::DIR.'/')) {
            return;
        }
        try {
            Storage::disk($meta['disk'] ?? MediaStorage::disk())->delete($meta['path']);
        } catch (\Throwable) {
            // best effort
        }
    }

    /** Clean a stored `custom` block read back from settings: only known variants with a known kind and a path under our directory. */
    public static function clean(mixed $custom): array
    {
        $out = [];
        foreach (self::VARIANTS as $v) {
            $m = is_array($custom) ? ($custom[$v] ?? null) : null;
            if (is_array($m) && in_array($m['kind'] ?? null, ['gif', 'webp', 'lottie'], true) && str_starts_with((string) ($m['path'] ?? ''), self::DIR.'/')) {
                $out[$v] = ['kind' => $m['kind'], 'path' => $m['path'], 'disk' => $m['disk'] ?? null, 'id' => $m['id'] ?? pathinfo($m['path'], PATHINFO_FILENAME)];
            }
        }

        return $out;
    }

    /**
     * What each mode actually shows: a mode with no upload uses the other mode's, so one file is enough.
     *
     * @return array{light: ?array, dark: ?array}
     */
    public static function resolved(array $custom): array
    {
        $light = $custom['light'] ?? null;
        $dark = $custom['dark'] ?? null;

        return ['light' => $light ?? $dark, 'dark' => $dark ?? $light];
    }

    /** Serve a stored Lottie JSON from our own origin (the CSP only lets scripts fetch from 'self'). */
    public static function lottieContents(string $id): ?string
    {
        if (preg_match('/^[0-9a-f\-]{36}$/', $id) !== 1) {
            return null;
        }
        $path = self::DIR.'/'.$id.'.json';
        foreach (array_unique([MediaStorage::disk(), 'public', 'wasabi', 'r2']) as $disk) {
            try {
                if (config('filesystems.disks.'.$disk) && Storage::disk($disk)->exists($path)) {
                    return Storage::disk($disk)->get($path);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
