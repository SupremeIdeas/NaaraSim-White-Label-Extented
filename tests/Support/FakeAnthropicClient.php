<?php

namespace Tests\Support;

use App\Services\AI\AnthropicClient;

/**
 * Test double for the Anthropic client — never touches the network. Returns a
 * canned JSON proposal so the PricingArchitect can be exercised deterministically,
 * including a deliberately-too-low price to prove MarginGuard clamps it.
 */
class FakeAnthropicClient extends AnthropicClient
{
    /**
     * @param  array<mixed>  $json  the object completeJson() should return
     */
    public function __construct(
        private array $json = [],
        private bool $on = true,
        public ?string $capturedSystem = null,
        public ?array $capturedMessages = null,
    ) {
    }

    public function enabled(): bool
    {
        return $this->on;
    }

    public function model(): string
    {
        return 'claude-sonnet-5';
    }

    public function completeJson(string $system, array $messages, int $maxTokens = 4096): array
    {
        $this->capturedSystem = $system;
        $this->capturedMessages = $messages;

        return $this->json;
    }
}
