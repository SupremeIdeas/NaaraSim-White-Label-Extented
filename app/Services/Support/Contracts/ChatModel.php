<?php

namespace App\Services\Support\Contracts;

/**
 * One round-trip to the chat model (Module 24). Kept behind a contract so the
 * NaaraCareAgent orchestrates the tool-use loop while tests inject a fake model
 * (no real HTTP). Returns the raw Anthropic-style response: a list of content
 * blocks plus a stop_reason.
 */
interface ChatModel
{
    /** Whether the model is configured (has an API key). */
    public function available(): bool;

    /**
     * @param  string  $system  the system prompt
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools  Anthropic tool schemas
     * @return array{stop_reason: string, content: array<int, array<string, mixed>>}
     */
    public function reply(string $system, array $messages, array $tools): array;
}
