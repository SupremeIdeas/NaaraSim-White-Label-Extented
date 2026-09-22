<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Marketing/Chat blueprint Phase A: real devices/browsers produce voice
 * recordings in far more formats than any fixed whitelist can keep up with
 * (audio/3gpp, audio/amr, audio/aac, a video/webm container wrapping an
 * audio-only track, and whatever the next phone model ships). Rather than
 * growing the accepted-mimetypes list forever, every accepted upload is
 * normalized here to one canonical format (mp3) before it's stored or
 * handed to Nia/any AI processing — a future format nobody anticipated
 * degrades gracefully instead of silently failing the upload again.
 *
 * Fails soft: if `ffmpeg` isn't on the host PATH, the original bytes/mime
 * are returned unchanged (logged once) rather than breaking voice notes
 * entirely on a host where it isn't installed yet.
 */
class AudioTranscoder
{
    private const CANONICAL_MIME = 'audio/mpeg';

    private const CANONICAL_EXTENSION = 'mp3';

    public function available(): bool
    {
        try {
            return Process::run('ffmpeg -version')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string} [bytes, mime] — transcoded to mp3
     *                                     when ffmpeg is available, otherwise
     *                                     the original bytes/mime unchanged.
     */
    public function toCanonical(string $bytes, string $sourceMime, string $sourceExtension): array
    {
        if (! $this->available()) {
            Log::warning('AudioTranscoder: ffmpeg not found on PATH, storing voice note untranscoded.', [
                'mime' => $sourceMime,
            ]);

            return [$bytes, $sourceMime];
        }

        $tmpDir = sys_get_temp_dir();
        $inPath = $tmpDir.'/'.Str::uuid()->toString().'.'.($sourceExtension ?: 'bin');
        $outPath = $tmpDir.'/'.Str::uuid()->toString().'.'.self::CANONICAL_EXTENSION;

        try {
            file_put_contents($inPath, $bytes);

            $result = Process::timeout(30)->run([
                'ffmpeg', '-y', '-i', $inPath, '-vn', '-acodec', 'libmp3lame', '-ar', '44100', '-b:a', '128k', $outPath,
            ]);

            if (! $result->successful() || ! is_file($outPath)) {
                Log::warning('AudioTranscoder: ffmpeg transcode failed, storing voice note untranscoded.', [
                    'mime' => $sourceMime, 'error' => $result->errorOutput(),
                ]);

                return [$bytes, $sourceMime];
            }

            return [file_get_contents($outPath), self::CANONICAL_MIME];
        } finally {
            @unlink($inPath);
            @unlink($outPath);
        }
    }

    public function canonicalExtension(): string
    {
        return self::CANONICAL_EXTENSION;
    }
}
