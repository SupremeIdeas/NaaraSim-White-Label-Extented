<?php

namespace App\Services\Support;

use App\Services\Support\Contracts\ChatModel;
use Illuminate\Support\Facades\Http;

/**
 * Production chat model (Module 24): the Anthropic Messages API with tool-use.
 * Gated on services.anthropic.api_key (reuses the maintenance-loop pattern); the
 * model id is env-driven so it can be tuned without a deploy.
 */
class ClaudeChatModel implements ChatModel
{
    public function available(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    public function reply(string $system, array $messages, array $tools): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 1024,
            'system' => $system,
            'tools' => $tools,
            'messages' => $messages,
        ])->throw()->json();

        return [
            'stop_reason' => $response['stop_reason'] ?? 'end_turn',
            'content' => $response['content'] ?? [],
        ];
    }
}
