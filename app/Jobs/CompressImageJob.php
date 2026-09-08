<?php

namespace App\Jobs;

use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side, authoritative image compression (BUILD-11 §3). Runs in the
 * background AFTER an upload is already stored — never inline, so it adds zero
 * latency to the upload response. Converts the stored raster to WebP and steps
 * quality down until the file lands at/under ~80KB or a sane quality floor is
 * hit, resizing first to the upload context's real display dimension.
 *
 * Safety net (§3.4): the compressed WebP overwrites the SAME path only on full
 * success, so the URL the caller already saved stays valid. If ANYTHING fails —
 * unreadable file, no GD/WebP, a decode error — the original upload is left
 * exactly as it was and still works. We never delete or truncate the original
 * before a good replacement is in hand.
 */
class CompressImageJob implements ShouldQueue
{
    use Queueable;

    /** ~80KB target (§3.1). */
    public const TARGET_BYTES = 80 * 1024;

    /** Don't chase an unreachable target into mush — stop here (§3.1). */
    public const QUALITY_FLOOR = 40;

    public const QUALITY_START = 82;

    public const QUALITY_STEP = 8;

    /** Retry once, then give up cleanly leaving the original in place (§3.4). */
    public int $tries = 2;

    public function __construct(
        public string $disk,
        public string $path,
        public string $context = '',
    ) {}

    public function handle(): void
    {
        // WebP encoding needs GD compiled with WebP. On a host without it we do
        // nothing — the original upload already works, which is the whole point.
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return;
        }

        try {
            // $this->disk is the disk MediaStorage already resolved for this
            // upload and passed in at dispatch (MediaStorage::storePublic) — we
            // must compress the exact file where MediaStorage put it, so this is
            // MediaStorage-routed, not a hardcoded disk.
            $storage = Storage::disk($this->disk);
            if (! $storage->exists($this->path)) {
                return; // deleted/replaced already — nothing to do.
            }

            $original = $storage->get($this->path);
            if (! is_string($original) || $original === '') {
                return;
            }

            // Already a small-enough WebP? Leave it — re-encoding only loses quality.
            if (strlen($original) <= self::TARGET_BYTES && $this->looksLikeWebp($original)) {
                return;
            }

            $webp = $this->toCompressedWebp($original, MediaStorage::contextMaxDimension($this->context));
            if ($webp === null) {
                return; // undecodable / not worth it — original stays.
            }

            // Only overwrite when the WebP actually wins on bytes. A rare image
            // that WebP can't beat (already-tight PNG line art, say) keeps its
            // original — which still displays fine — rather than growing.
            if (strlen($webp) >= strlen($original)) {
                return;
            }

            // Overwrite in place. Cloud disks get an explicit image/webp
            // content-type; on the local disk <img> decodes by bytes anyway, so
            // the URL/extension stays stable and the image still renders.
            $storage->put($this->path, $webp, [
                'visibility' => 'public',
                'ContentType' => 'image/webp',
                'mimetype' => 'image/webp',
            ]);
        } catch (\Throwable $e) {
            // §3.4: log and leave the original untouched — never break the image.
            Log::warning('[media] image compression failed, original kept: '.$e->getMessage(), [
                'disk' => $this->disk,
                'path' => $this->path,
                'context' => $this->context,
            ]);
        }
    }

    /**
     * Decode → resize to fit the context's max edge → step WebP quality down to
     * the ~80KB target or the quality floor. Returns null if the bytes can't be
     * decoded as an image, so the caller leaves the original in place.
     */
    private function toCompressedWebp(string $bytes, int $maxEdge): ?string
    {
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }

        try {
            $img = $this->resizeToFit($img, $maxEdge);

            // Preserve transparency (logos/avatars) through the WebP encode.
            imagepalettetotruecolor($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);

            $best = null;
            for ($q = self::QUALITY_START; $q >= self::QUALITY_FLOOR; $q -= self::QUALITY_STEP) {
                $encoded = $this->encodeWebp($img, $q);
                if ($encoded === null) {
                    continue;
                }
                $best = $encoded;
                if (strlen($encoded) <= self::TARGET_BYTES) {
                    break; // hit target — stop, don't over-compress.
                }
            }

            return $best;
        } finally {
            imagedestroy($img);
        }
    }

    /** Scale down so the longest edge fits $maxEdge; never upscales. */
    private function resizeToFit(\GdImage $img, int $maxEdge): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $longest = max($w, $h);
        if ($longest <= $maxEdge || $longest === 0) {
            return $img;
        }

        $scale = $maxEdge / $longest;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $dst;
    }

    private function encodeWebp(\GdImage $img, int $quality): ?string
    {
        ob_start();
        $ok = imagewebp($img, null, $quality);
        $data = (string) ob_get_clean();

        return ($ok && $data !== '') ? $data : null;
    }

    /** RIFF/WEBP magic bytes — a cheap "is this already a WebP?" check. */
    private function looksLikeWebp(string $bytes): bool
    {
        return strlen($bytes) >= 12
            && str_starts_with($bytes, 'RIFF')
            && substr($bytes, 8, 4) === 'WEBP';
    }
}
