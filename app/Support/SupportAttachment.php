<?php

namespace App\Support;

/**
 * Evidence attachments for NaaraCare (owner request). Customers attach a
 * screenshot / photo / PDF to a support message; the AI reads it to diagnose.
 *
 * This is the ONE place that knows what evidence is allowed and how to turn a
 * stored file into an Anthropic content block. Images and PDFs only — the exact
 * media types Claude can natively see — so nothing executable or unreadable is
 * ever accepted or forwarded to the model.
 */
class SupportAttachment
{
    /** Vision-capable image types Claude accepts. */
    public const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /** Document types Claude can read. */
    public const DOC_MIMES = ['application/pdf'];

    /** 5 MB — generous for a screenshot or a short PDF, small enough to stay fast. */
    public const MAX_KB = 5120;

    /** Livewire/validator rule for an uploaded piece of evidence. */
    public static function uploadRules(): array
    {
        return [
            'file',
            'mimetypes:'.implode(',', [...self::IMAGE_MIMES, ...self::DOC_MIMES]),
            'max:'.self::MAX_KB,
        ];
    }

    public static function acceptAttribute(): string
    {
        return implode(',', [...self::IMAGE_MIMES, ...self::DOC_MIMES]);
    }

    public static function isImage(?string $mime): bool
    {
        return in_array((string) $mime, self::IMAGE_MIMES, true);
    }

    public static function isSupported(?string $mime): bool
    {
        return in_array((string) $mime, [...self::IMAGE_MIMES, ...self::DOC_MIMES], true);
    }

    /**
     * Build the Anthropic content block for a stored attachment so the agent can
     * SEE it. Images become an `image` block, PDFs a `document` block; anything
     * else (or unreadable bytes) returns null and is simply not forwarded.
     *
     * @return array<string, mixed>|null
     */
    public static function toContentBlock(string $bytes, ?string $mime): ?array
    {
        if (! self::isSupported($mime) || $bytes === '') {
            return null;
        }

        return [
            'type' => self::isImage($mime) ? 'image' : 'document',
            'source' => [
                'type' => 'base64',
                'media_type' => $mime,
                'data' => base64_encode($bytes),
            ],
        ];
    }
}
