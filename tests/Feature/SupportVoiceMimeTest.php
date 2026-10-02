<?php

namespace Tests\Feature;

use App\Livewire\SupportChat;
use App\Models\User;
use App\Services\Support\AudioTranscoder;
use App\Services\Support\Contracts\VoiceSynthesizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\FakeVoiceSynthesizer;
use Tests\TestCase;

/**
 * Marketing/Chat blueprint Phase A. Real Android/iOS devices and browsers
 * commonly produce audio/3gpp, audio/amr, audio/aac, and a video/webm
 * container wrapping an audio-only recording — none of which the original
 * mimetypes list covered, which is very likely why a granted, working
 * microphone still produced a rejected upload.
 */
class SupportVoiceMimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer);
    }

    private function fakeAudio(string $name, string $mime): UploadedFile
    {
        return UploadedFile::fake()->create($name, 50, $mime);
    }

    public function test_a_wide_realistic_device_mime_matrix_is_now_accepted(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['note.aac', 'audio/aac'],
            ['note.3gp', 'audio/3gpp'],
            ['note.amr', 'audio/amr'],
            ['note.webm', 'video/webm'], // browsers wrapping audio-only in a video container
        ] as [$name, $mime]) {
            Livewire::actingAs($user)->test(SupportChat::class)
                ->set('voiceNote', $this->fakeAudio($name, $mime))
                ->call('sendVoice')
                ->assertHasNoErrors('voiceNote');
        }
    }

    public function test_a_genuinely_unsupported_type_is_still_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(SupportChat::class)
            ->set('voiceNote', UploadedFile::fake()->create('note.exe', 50, 'application/x-msdownload'))
            ->call('sendVoice')
            ->assertHasErrors('voiceNote');
    }

    public function test_transcoder_falls_back_gracefully_when_ffmpeg_is_unavailable(): void
    {
        // This dev/CI sandbox has no ffmpeg binary — confirms the fallback
        // path (never a hard failure) rather than assuming ffmpeg exists.
        $transcoder = app(AudioTranscoder::class);
        $this->assertFalse($transcoder->available());

        [$bytes, $mime] = $transcoder->toCanonical('RAWBYTES', 'audio/aac', 'aac');

        $this->assertSame('RAWBYTES', $bytes);
        $this->assertSame('audio/aac', $mime);
    }
}
