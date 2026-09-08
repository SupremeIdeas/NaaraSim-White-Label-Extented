<?php

namespace Tests\Support;

use App\Services\Support\Contracts\ChatModel;

/**
 * A scriptable ChatModel for tests (Module 24) — no HTTP. Returns the queued
 * responses in order; each response is an Anthropic-style
 * ['stop_reason' => ..., 'content' => [...]] array. Records the messages it was
 * given so tests can assert tool results were fed back.
 */
class FakeChatModel implements ChatModel
{
    /** @var array<int, array{stop_reason: string, content: array}> */
    public array $script;

    public bool $isAvailable;

    /** @var array<int, array> the $messages passed to each reply() call */
    public array $calls = [];

    public function __construct(array $script = [], bool $available = true)
    {
        $this->script = $script;
        $this->isAvailable = $available;
    }

    public function available(): bool
    {
        return $this->isAvailable;
    }

    public function reply(string $system, array $messages, array $tools): array
    {
        $this->calls[] = $messages;

        return array_shift($this->script) ?? ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'ok']]];
    }

    /** Helper: a plain text turn. */
    public static function text(string $text): array
    {
        return ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /** Helper: a tool-use turn. */
    public static function toolUse(string $id, string $name, array $input): array
    {
        return ['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]]];
    }
}
