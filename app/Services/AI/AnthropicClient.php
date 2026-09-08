<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Anthropic Messages API client (blueprint Section 29 + the AI Pricing
 * Architect). It lights up ONLY when an Anthropic API key is active — the key is
 * admin-managed via ProviderKeys (services.anthropic.api_key) or .env, never
 * hard-coded (money-safety rule 10). Every AI feature checks enabled() first and
 * degrades gracefully when the key is absent, so the platform runs fine without
 * it and simply offers the manual margin controls instead.
 */
class AnthropicClient
{
    /** True when an Anthropic key is configured (admin panel or .env). */
    public function enabled(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-sonnet-5');
    }

    /**
     * Send a single-turn Messages request and return the concatenated text of
     * the assistant reply. Throws if the key is missing or the call fails — the
     * caller is responsible for catching and surfacing a friendly message.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096, float $temperature = 0.2): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $base = rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.api_key'),
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])->timeout(120)->post($base.'/messages', [
            'model' => $this->model(),
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => $system,
            'messages' => $messages,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: HTTP '.$response->status());
        }

        // Messages API returns content as an array of blocks; concatenate text.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw new RuntimeException('Anthropic API returned an empty response.');
        }

        return $text;
    }

    /**
     * Convenience: ask for a JSON object and decode it. Tolerates the model
     * wrapping the JSON in prose or a ```json fence by extracting the outermost
     * object. Throws if nothing decodes.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<mixed>
     */
    public function completeJson(string $system, array $messages, int $maxTokens = 4096): array
    {
        $raw = $this->complete($system, $messages, $maxTokens);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Extract the first {...} block if the model added surrounding text.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Anthropic API did not return valid JSON.');
    }
}
