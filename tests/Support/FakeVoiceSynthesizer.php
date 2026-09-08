<?php

namespace Tests\Support;

use App\Services\Support\Contracts\VoiceSynthesizer;

/** A no-HTTP voice synthesizer for tests (Module 25). */
class FakeVoiceSynthesizer implements VoiceSynthesizer
{
    public function __construct(
        public bool $isAvailable = true,
        public ?string $audio = 'FAKE_MP3_BYTES',
        public ?string $transcript = 'transcribed voice note',
    ) {}

    public function available(): bool
    {
        return $this->isAvailable;
    }

    public function synthesize(string $text): ?string
    {
        return $this->audio;
    }

    public function transcribe(string $audioBytes, string $mime): ?string
    {
        return $this->transcript;
    }
}
