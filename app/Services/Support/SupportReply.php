<?php

namespace App\Services\Support;

use App\Jobs\RenderVoiceJob;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Support\SpendGate;

/**
 * Creates an outgoing support message (from the AI or a human) and, when
 * appropriate, queues a spoken version (Module 25). Voice is gated two ways:
 *   - it only happens if ElevenLabs is configured, and
 *   - only for PAYING customers (SpendGate) — voice costs money to generate, so
 *     new/free users get text only at first glance.
 * The text reply is always saved immediately regardless of voice.
 */
class SupportReply
{
    /**
     * @param  'assistant'|'staff'  $role
     * @param  array<string, mixed>|null  $meta
     */
    public function deliver(SupportConversation $conversation, string $role, string $body, ?array $meta = null, bool $withVoice = true): SupportMessage
    {
        $wantsVoice = $withVoice
            && app(VoiceSynthesizer::class)->available()
            && SpendGate::hasPurchased($conversation->user);

        $message = $conversation->messages()->create([
            'role' => $role,
            'body' => $body,
            'meta' => $meta,
            'voice_status' => $wantsVoice ? 'pending' : null,
        ]);

        if ($wantsVoice) {
            RenderVoiceJob::dispatch($message->id);
        }

        return $message;
    }
}
