<?php

namespace App\Services\Support;

use App\Services\Support\Contracts\VoiceSynthesizer;
use Illuminate\Support\Facades\Http;

/**
 * Production voice via ElevenLabs (Module 25). Gated on the admin-set key +
 * voice id. TTS uses the configured model (eleven_v3 gives the most expressive,
 * human delivery and honours audio tags like [laughs]/[exhales]). Fails soft —
 * returns null rather than throwing — so a voice hiccup never breaks the text
 * reply that always accompanies it.
 */
class ElevenLabsVoice implements VoiceSynthesizer
{
    public function available(): bool
    {
        return filled(config('services.elevenlabs.api_key'))
            && filled(config('services.elevenlabs.voice_id'));
    }

    public function synthesize(string $text): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $base = rtrim((string) config('services.elevenlabs.base_url'), '/');
        $voice = config('services.elevenlabs.voice_id');

        try {
            $response = Http::withHeaders([
                'xi-api-key' => config('services.elevenlabs.api_key'),
                'accept' => 'audio/mpeg',
                'content-type' => 'application/json',
            ])->timeout(60)->post("{$base}/text-to-speech/{$voice}", [
                'text' => $text,
                'model_id' => config('services.elevenlabs.model', 'eleven_v3'),
                'voice_settings' => ['stability' => 0.4, 'similarity_boost' => 0.75],
            ]);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function transcribe(string $audioBytes, string $mime): ?string
    {
        if (! filled(config('services.elevenlabs.api_key'))) {
            return null;
        }

        $base = rtrim((string) config('services.elevenlabs.base_url'), '/');

        try {
            $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.api_key')])
                ->attach('file', $audioBytes, 'note.'.$this->ext($mime))
                ->timeout(60)
                ->post("{$base}/speech-to-text", ['model_id' => 'scribe_v1']);

            return $response->successful() ? ($response->json('text') ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function ext(string $mime): string
    {
        return match ($mime) {
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            default => 'bin',
        };
    }
}
