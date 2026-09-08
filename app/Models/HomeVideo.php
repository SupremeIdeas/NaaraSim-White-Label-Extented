<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Homepage video entry (BUILD-3 §8). A title + a video (self-hosted upload URL
 * or a YouTube id), a poster image, and an orientation. The public section
 * lazy-loads the actual player only when its modal opens.
 */
class HomeVideo extends Model
{
    protected $fillable = [
        'title', 'source_type', 'youtube_id', 'video_url',
        'poster_url', 'orientation', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Normalise a pasted YouTube URL or id down to the bare 11-char video id. */
    public static function parseYouTubeId(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        // Already a bare id.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        // youtu.be/<id>, watch?v=<id>, /embed/<id>, /shorts/<id>
        if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Whether this entry has a usable video source. */
    public function isPlayable(): bool
    {
        return ($this->source_type === 'youtube' && filled($this->youtube_id))
            || ($this->source_type === 'upload' && filled($this->video_url));
    }

    /** The privacy-friendly YouTube embed URL (no cookies, no related videos). */
    public function youTubeEmbedUrl(): ?string
    {
        if ($this->source_type !== 'youtube' || ! filled($this->youtube_id)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id.'?autoplay=1&rel=0&modestbranding=1&playsinline=1';
    }

    /** A sensible poster fallback: the admin's poster, else the YouTube thumb. */
    public function posterOrFallback(): ?string
    {
        if (filled($this->poster_url)) {
            return $this->poster_url;
        }
        if ($this->source_type === 'youtube' && filled($this->youtube_id)) {
            return 'https://i.ytimg.com/vi/'.Str::of($this->youtube_id)->toString().'/hqdefault.jpg';
        }

        return null;
    }
}
