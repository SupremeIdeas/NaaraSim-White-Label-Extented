<?php

namespace App\Support;

use App\Jobs\CompressImageJob;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One place for admin media uploads (logos, icons, etc.) with a storage
 * fallback: use Wasabi when it's configured, otherwise the server's own public
 * disk (blueprint Section 3.1 + operator request — the platform must keep
 * working on cPanel with no Wasabi keys).
 *
 * Researched upload limits (safe defaults for logos/icons):
 *   - Raster (PNG/JPEG/WebP/GIF): max 2 MB. Logos are small; 2 MB is generous.
 *   - SVG: max 512 KB AND sanitized (SVG is executable XML — strip scripts,
 *     event handlers, and foreignObject before it's ever served to a user).
 *   - Recommended source size: ~512px on the long edge; transparent PNG or SVG.
 */
class MediaStorage
{
    /** @var list<string> */
    public const RASTER_TYPES = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public const MAX_RASTER_KB = 2048; // 2 MB

    public const MAX_SVG_KB = 512;

    /** Motion banners (blueprint Module 31 + operator request). Capped small so
     *  the "More" zone stays fast: mp4/webm, 10 MB max. Videos are stored as-is
     *  (never converted) and always served muted-looping with an image poster. */
    public const VIDEO_TYPES = ['mp4', 'webm'];

    public const MAX_VIDEO_KB = 10240; // 10 MB

    public static function wasabiConfigured(): bool
    {
        return filled(config('filesystems.disks.wasabi.key'))
            && filled(config('filesystems.disks.wasabi.secret'))
            && filled(config('filesystems.disks.wasabi.bucket'));
    }

    /** True once R2 has creds + endpoint (BUILD-11 §4). */
    public static function r2Configured(): bool
    {
        return filled(config('filesystems.disks.r2.key'))
            && filled(config('filesystems.disks.r2.secret'))
            && filled(config('filesystems.disks.r2.bucket'))
            && filled(config('filesystems.disks.r2.endpoint'));
    }

    /**
     * Admin's chosen primary object store: 'r2' | 'wasabi' | 'public' | 'auto'.
     * 'auto' (the default) prefers R2, then Wasabi, then local — matching the
     * stated preference order. Never a hard dependency: an explicit choice that
     * isn't actually configured falls through to auto.
     */
    public static function primaryPreference(): string
    {
        try {
            $v = (string) Setting::getValue('media.primary_disk', 'auto');
        } catch (\Throwable) {
            return 'auto';
        }

        return in_array($v, ['r2', 'wasabi', 'public', 'auto'], true) ? $v : 'auto';
    }

    /** Disk to store PUBLIC media on: R2 → Wasabi → local public (per config). */
    public static function disk(): string
    {
        return self::resolveDisk(publicContext: true);
    }

    /**
     * Disk for PRIVATE files (e.g. GDPR data exports): R2 → Wasabi → local disk —
     * never web-accessible directly. Reached only through an owner-authenticated
     * download route (object stores serve these via short-lived signed URLs).
     */
    public static function privateDisk(): string
    {
        return self::resolveDisk(publicContext: false);
    }

    /**
     * Resolve the active disk at runtime from what's actually configured (BUILD-11
     * §4) — identical logic on cPanel and VPS, nothing environment-specific. A
     * public context needs R2's public serving URL before R2 can win.
     */
    private static function resolveDisk(bool $publicContext): string
    {
        $pref = self::primaryPreference();
        $r2 = self::r2Configured() && (! $publicContext || filled(config('filesystems.disks.r2.url')));
        $wasabi = self::wasabiConfigured();
        $localFallback = $publicContext ? 'public' : 'local';

        // An explicit, actually-configured admin choice wins.
        if ($pref === 'r2' && $r2) {
            return 'r2';
        }
        if ($pref === 'wasabi' && $wasabi) {
            return 'wasabi';
        }
        if ($pref === 'public') {
            return $localFallback;
        }

        // auto (or the chosen store isn't configured): R2 → Wasabi → local.
        return $r2 ? 'r2' : ($wasabi ? 'wasabi' : $localFallback);
    }

    /** Raster formats the server-side WebP pass can safely process. GIF is
     *  excluded on purpose — GD flattens animation to a single frame. */
    public const COMPRESSIBLE_TYPES = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Upload contexts (the storePublic $dir) that must NOT be aggressively
     * compressed — legibility/originals matter more than bytes (BUILD-11 §3.3).
     * KYC/identity documents don't currently flow through storePublic at all,
     * but this keeps them excluded by design if that ever changes.
     *
     * @var list<string>
     */
    public const NO_COMPRESS_CONTEXTS = ['kyc', 'documents', 'identity', 'exports'];

    /**
     * The longest-edge cap (px) for a given upload context (BUILD-11 §3.1) — no
     * reason to keep 4000px for an avatar that renders at 200px. Heroes/banners/
     * covers stay large enough to look crisp; avatars/logos/icons shrink hard.
     * Anything unmapped falls back to a sensible general photo size.
     */
    public static function contextMaxDimension(string $context): int
    {
        return match ($context) {
            'avatars', 'support', 'merchant-logos', 'service-icons', 'giftcards' => 512,
            'brand', 'splash', 'app-export', 'numbers-bento' => 1024,
            default => 1600, // heroes, banners, blog covers, page/site images, chat photos
        };
    }

    /** Whether the server-side WebP pass should run for this file + context. */
    public static function shouldCompress(string $ext, string $context): bool
    {
        return in_array(strtolower($ext), self::COMPRESSIBLE_TYPES, true)
            && ! in_array($context, self::NO_COMPRESS_CONTEXTS, true);
    }

    /** Livewire/validator rule for an uploaded image or SVG. */
    public static function uploadRules(): array
    {
        return ['file', 'mimes:'.implode(',', [...self::RASTER_TYPES, 'svg']), 'max:'.self::MAX_RASTER_KB];
    }

    public static function acceptAttribute(): string
    {
        return 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
    }

    /** Livewire/validator rule for an uploaded banner video (mp4/webm, ≤10 MB). */
    public static function videoUploadRules(): array
    {
        return ['file', 'mimetypes:video/mp4,video/webm', 'max:'.self::MAX_VIDEO_KB];
    }

    public static function videoAcceptAttribute(): string
    {
        return 'video/mp4,video/webm';
    }

    /**
     * Store a public asset and return its public URL. SVGs are sanitized; a
     * new random filename avoids collisions and guessing.
     */
    public static function storePublic(UploadedFile $file, string $dir = 'media'): string
    {
        $disk = self::disk();
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $name = Str::uuid()->toString().'.'.$ext;
        $path = trim($dir, '/').'/'.$name;

        if ($ext === 'svg') {
            Storage::disk($disk)->put($path, self::sanitizeSvg((string) file_get_contents($file->getRealPath())), 'public');
        } else {
            Storage::disk($disk)->putFileAs($dir, $file, $name, 'public');

            // Authoritative server-side WebP pass (BUILD-11 §3): queued, never
            // inline, so it adds zero latency here. It overwrites this same path
            // on success — the URL we return stays valid — and leaves the
            // original untouched if it fails. KYC/quality-sensitive contexts and
            // GIFs are skipped by shouldCompress().
            $context = trim($dir, '/');
            if (self::shouldCompress($ext, $context)) {
                CompressImageJob::dispatch($disk, $path, $context);
            }
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Store already-in-memory bytes (e.g. a transcoded voice note — there's no
     * UploadedFile to hand storePublic() once ffmpeg has run) to the public
     * disk and return its URL. No image-compression pass — that's only ever
     * relevant to storePublic()'s raster-image path.
     */
    public static function storePublicBytes(string $bytes, string $extension, string $dir = 'media'): string
    {
        $disk = self::disk();
        $path = trim($dir, '/').'/'.Str::uuid()->toString().'.'.trim($extension, '.');
        Storage::disk($disk)->put($path, $bytes, 'public');

        return Storage::disk($disk)->url($path);
    }

    /**
     * The storage-relative path of a LOCAL public URL (…/storage/<path>), or
     * null if the URL isn't a local public asset (already on Wasabi/a CDN, or
     * an external URL we don't own).
     */
    public static function localPublicPath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // Only our own /storage/ paths are local public assets.
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (! str_contains($path, '/storage/')) {
            return null;
        }
        $rel = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');

        return $rel !== '' && Storage::disk('public')->exists($rel) ? $rel : null;
    }

    /**
     * Move a LOCAL public asset to Wasabi and return its new cloud URL. No-op
     * (returns null) unless Wasabi is configured AND the URL is a local file we
     * own. The local copy is left in place — a later cleanup can prune it — so a
     * failed migration never loses the asset. Idempotent: an already-cloud URL
     * returns null and is skipped.
     */
    public static function migrateLocalUrlToCloud(string $url): ?string
    {
        if (! self::wasabiConfigured()) {
            return null;
        }
        $rel = self::localPublicPath($url);
        if ($rel === null) {
            return null;
        }

        $contents = Storage::disk('public')->get($rel);
        Storage::disk('wasabi')->put($rel, $contents, 'public');

        return Storage::disk('wasabi')->url($rel);
    }

    /**
     * Neutralise an SVG before it is served to users: remove scripts, inline
     * event handlers, foreignObject, and javascript: URLs.
     */
    public static function sanitizeSvg(string $svg): string
    {
        // Scripts (paired AND self-closing/unclosed), and <style> which can smuggle
        // @import / url(javascript:) on legacy engines.
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<script\b[^>]*/?>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? $svg;

        // Inline event handlers — quoted "…", '…', AND unquoted (on…=alert(1)).
        // The unquoted form was the gap: <svg onload=alert(1)> is the classic SVG
        // XSS payload and slipped past a quotes-only rule.
        $svg = preg_replace('#\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg) ?? $svg;

        // javascript: URLs on href/xlink:href — quoted and unquoted. Tolerate the
        // whitespace/newline obfuscation browsers ignore inside the scheme.
        $svg = preg_replace('#(?:xlink:href|href)\s*=\s*"\s*j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:[^"]*"#i', '', $svg) ?? $svg;
        $svg = preg_replace("#(?:xlink:href|href)\s*=\s*'\s*j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:[^']*'#i", '', $svg) ?? $svg;
        $svg = preg_replace('#(?:xlink:href|href)\s*=\s*j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:[^\s>]*#i', '', $svg) ?? $svg;

        return $svg;
    }
}
