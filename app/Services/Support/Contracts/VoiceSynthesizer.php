<?php

namespace App\Services\Support\Contracts;

/**
 * Text-to-speech (and best-effort speech-to-text) for support (Module 25).
 * Behind a contract so voice can be faked in tests (no real HTTP) and swapped
 * without touching callers.
 */
interface VoiceSynthesizer
{
    /** Whether voice is configured (API key + voice id). */
    public function available(): bool;

    /** Synthesize text to speech; returns raw audio bytes (mp3) or null on failure. */
    public function synthesize(string $text): ?string;

    /** Best-effort transcription of an audio clip; returns text or null. */
    public function transcribe(string $audioBytes, string $mime): ?string;
}
